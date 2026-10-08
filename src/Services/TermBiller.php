<?php

declare(strict_types=1);

namespace HoceineEl\UsageBilling\Services;

use Carbon\CarbonImmutable;
use HoceineEl\UsageBilling\Contracts\BillingParty;
use HoceineEl\UsageBilling\Enums\InvoiceStatus;
use HoceineEl\UsageBilling\Enums\SubscriptionEventType;
use HoceineEl\UsageBilling\Events\InvoiceIssued;
use HoceineEl\UsageBilling\Models\Invoice;
use HoceineEl\UsageBilling\Models\Plan;
use HoceineEl\UsageBilling\Models\Subscription;
use HoceineEl\UsageBilling\Support\Period;
use HoceineEl\UsageBilling\UsageBilling;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Sells subscriptions as prepaid terms.
 *
 * A term invoice is issued before the term runs, and the term only starts once
 * that invoice is paid. Nothing is consumed before it has been paid for, which
 * is the whole point of the model.
 *
 * Usage modules are included allowances rather than billable lines here — the
 * plan price is the price — but the period's consumption is still snapshotted
 * onto the invoice so the cabinet can see what it got for the money.
 */
class TermBiller
{
    public function __construct(
        private readonly InvoiceNumberGenerator $numbers,
        private readonly UsageSynchronizer $synchronizer,
    ) {}

    /**
     * Invoice the next term. One term facture is open at a time: while one is
     * awaiting payment it is returned whatever month the run lands in, so a
     * renewal job can never bill an unpaid trial a fresh term every month.
     */
    public function issueTermInvoice(Subscription $subscription, ?CarbonImmutable $termStart = null): ?Invoice
    {
        return Cache::store(config('usage-billing.cache.store'))->lock("usage-billing:issue-term:{$subscription->getKey()}", 30)->block(
            10,
            fn (): ?Invoice => $this->openTermInvoice($subscription) ?? $this->createTermInvoice($subscription, $termStart),
        );
    }

    /**
     * The term facture still awaiting payment, if any. Upgrade supplements are
     * not terms and never count.
     */
    public function openTermInvoice(Subscription $subscription): ?Invoice
    {
        return $subscription->invoices()
            ->awaitingPayment()
            ->oldest('issued_at')
            ->get()
            ->first(fn (Invoice $invoice): bool => static::termOf($invoice) !== null);
    }

    /**
     * The term a facture sells, read from the intent stored on it.
     *
     * @return array{starts_at: CarbonImmutable, ends_at: CarbonImmutable}|null
     */
    public static function termOf(Invoice $invoice): ?array
    {
        $intent = json_decode((string) $invoice->notes, true);

        if (! is_array($intent) || ! isset($intent['term_starts_at'], $intent['term_ends_at'])) {
            return null;
        }

        return [
            'starts_at' => CarbonImmutable::parse($intent['term_starts_at'])->startOfDay(),
            'ends_at' => CarbonImmutable::parse($intent['term_ends_at'])->endOfDay(),
        ];
    }

    protected function createTermInvoice(Subscription $subscription, ?CarbonImmutable $termStart = null): ?Invoice
    {
        $plan = $subscription->plan;

        if (! $plan instanceof Plan) {
            return null;
        }

        $subscriber = $subscription->subscriber;

        if (! $subscriber instanceof Model) {
            return null;
        }

        $termStart = $termStart ?? $this->nextTermStart($subscription);
        $termEnd = $plan->termEndFrom($termStart);
        $dueAt = $this->termDueDate($subscription, $plan);

        $invoice = DB::transaction(function () use ($subscription, $subscriber, $plan, $termStart, $termEnd, $dueAt): Invoice {
            /** @var Invoice $invoice */
            $invoice = UsageBilling::query('invoice')->create([
                ...$this->buyerSnapshot($subscriber),
                'subscriber_type' => $subscriber->getMorphClass(),
                'subscriber_id' => $subscriber->getKey(),
                'subscription_id' => $subscription->getKey(),
                'period' => $termStart->format('Y-m'),
                'status' => InvoiceStatus::Draft,
                'currency' => $plan->currency ?? UsageBilling::currency(),
                'tva_rate' => $plan->tva_rate,
                'usage_snapshot' => $this->usageSnapshot($subscription),
            ]);

            $seats = $plan->isSeatBased() ? $subscription->seatsForNextTerm() : null;

            $this->writeTermLines($invoice, $plan, $termStart, $termEnd, $seats);
            $this->recalculateTotals($invoice);

            $invoice->forceFill([
                'number' => $this->numbers->next(),
                'status' => InvoiceStatus::Issued,
                'issued_at' => now(),
                'due_at' => $dueAt,
                'notes' => json_encode(array_filter([
                    'term_starts_at' => $termStart->toDateString(),
                    'term_ends_at' => $termEnd->toDateString(),
                    'seats' => $seats,
                ], fn (mixed $value): bool => $value !== null)),
            ])->save();

            $subscription->events()->create([
                'type' => SubscriptionEventType::InvoiceIssued,
                'to_value' => $invoice->number,
                'meta' => [
                    'kind' => 'term',
                    'term_starts_at' => $termStart->toDateString(),
                    'term_ends_at' => $termEnd->toDateString(),
                    'total_ttc' => (float) $invoice->total_ttc,
                ],
            ]);

            return $invoice;
        });

        InvoiceIssued::dispatch($invoice);

        return $invoice;
    }

    /**
     * A trial facture falls due when the trial ends, never later, so it cannot
     * still be "not yet due" once access has run out.
     */
    protected function termDueDate(Subscription $subscription, Plan $plan): CarbonImmutable
    {
        $dueAt = CarbonImmutable::now()->addDays((int) $plan->payment_term_days);
        $trialEndsAt = $this->unpaidTrialEnd($subscription);

        return $trialEndsAt !== null && $dueAt->isAfter($trialEndsAt) ? $trialEndsAt : $dueAt;
    }

    /**
     * When a trial that has never been paid for ends, or null once a term has
     * been bought.
     */
    protected function unpaidTrialEnd(Subscription $subscription): ?CarbonImmutable
    {
        return $subscription->onTrial() && $subscription->ends_at === null
            ? $subscription->trial_ends_at
            : null;
    }

    /**
     * Bill the difference when a cabinet outgrows its plan mid-term, charged
     * only for the months it has left rather than a fresh full year.
     */
    public function issueUpgradeInvoice(Subscription $subscription, Plan $target, ?int $seats = null): ?Invoice
    {
        $current = $subscription->plan;
        $subscriber = $subscription->subscriber;

        if (! $current instanceof Plan || ! $subscriber instanceof Model) {
            return null;
        }

        $seats = $target->isSeatBased() ? $target->billableSeats($seats ?? $subscription->seats) : null;
        $difference = $target->termPriceFor($seats) - $subscription->termPrice();

        if ($difference <= 0) {
            return null;
        }

        $amount = round($difference * $this->remainingTermRatio($subscription), 2);

        if ($amount <= 0) {
            return null;
        }

        $invoice = DB::transaction(function () use ($subscription, $subscriber, $current, $target, $amount, $seats): Invoice {
            /** @var Invoice $invoice */
            $invoice = UsageBilling::query('invoice')->create([
                ...$this->buyerSnapshot($subscriber),
                'subscriber_type' => $subscriber->getMorphClass(),
                'subscriber_id' => $subscriber->getKey(),
                'subscription_id' => $subscription->getKey(),
                'period' => now()->format('Y-m'),
                'status' => InvoiceStatus::Draft,
                'currency' => $target->currency ?? UsageBilling::currency(),
                'tva_rate' => $target->tva_rate,
            ]);

            $invoice->lines()->create([
                'description' => __('usage-billing::billing.lines.upgrade', [
                    'from' => $current->displayName(),
                    'to' => $target->displayName(),
                    'until' => $subscription->ends_at?->translatedFormat('d/m/Y') ?? '',
                ]),
                'quantity' => 1,
                'unit_price_ht' => $amount,
                'amount_ht' => $amount,
                'sort_order' => 0,
            ]);

            $this->recalculateTotals($invoice);

            $invoice->forceFill([
                'number' => $this->numbers->next(),
                'status' => InvoiceStatus::Issued,
                'issued_at' => now(),
                'due_at' => now()->addDays((int) $target->payment_term_days),
                'notes' => json_encode(array_filter([
                    'upgrade_to_plan_id' => $target->getKey(),
                    'seats' => $seats,
                ], fn (mixed $value): bool => $value !== null)),
            ])->save();

            $subscription->events()->create([
                'type' => SubscriptionEventType::InvoiceIssued,
                'to_value' => $invoice->number,
                'meta' => [
                    'kind' => 'upgrade',
                    'from_plan' => $current->slug,
                    'to_plan' => $target->slug,
                    'total_ttc' => (float) $invoice->total_ttc,
                ],
            ]);

            return $invoice;
        });

        InvoiceIssued::dispatch($invoice);

        return $invoice;
    }

    /**
     * Bill extra seats bought mid-term, prorated to the months left. The seats
     * are granted once this facture is paid, like any other purchase.
     */
    public function issueSeatInvoice(Subscription $subscription, int $additional): ?Invoice
    {
        $plan = $subscription->plan;
        $subscriber = $subscription->subscriber;

        if ($additional < 1 || ! $plan instanceof Plan || ! $plan->isSeatBased() || ! $subscriber instanceof Model) {
            return null;
        }

        $ratio = $this->remainingTermRatio($subscription);
        $unitPrice = round((float) $plan->seat_price_ht * $ratio, 2);

        if ($unitPrice <= 0) {
            return null;
        }

        $invoice = DB::transaction(function () use ($subscription, $subscriber, $plan, $additional, $unitPrice): Invoice {
            /** @var Invoice $invoice */
            $invoice = UsageBilling::query('invoice')->create([
                ...$this->buyerSnapshot($subscriber),
                'subscriber_type' => $subscriber->getMorphClass(),
                'subscriber_id' => $subscriber->getKey(),
                'subscription_id' => $subscription->getKey(),
                'period' => now()->format('Y-m'),
                'status' => InvoiceStatus::Draft,
                'currency' => $plan->currency ?? UsageBilling::currency(),
                'tva_rate' => $plan->tva_rate,
            ]);

            $invoice->lines()->create([
                'description' => __('usage-billing::billing.lines.seats_added', [
                    'count' => $additional,
                    'plan' => $plan->displayName(),
                    'until' => $subscription->ends_at?->translatedFormat('d/m/Y') ?? '',
                ]),
                'quantity' => $additional,
                'unit_price_ht' => $unitPrice,
                'amount_ht' => round($additional * $unitPrice, 2),
                'sort_order' => 0,
            ]);

            $this->recalculateTotals($invoice);

            $invoice->forceFill([
                'number' => $this->numbers->next(),
                'status' => InvoiceStatus::Issued,
                'issued_at' => now(),
                'due_at' => now()->addDays((int) $plan->payment_term_days),
                'notes' => json_encode(['add_seats' => $additional]),
            ])->save();

            $subscription->events()->create([
                'type' => SubscriptionEventType::InvoiceIssued,
                'to_value' => $invoice->number,
                'meta' => [
                    'kind' => 'seats',
                    'seats' => $additional,
                    'total_ttc' => (float) $invoice->total_ttc,
                ],
            ]);

            return $invoice;
        });

        InvoiceIssued::dispatch($invoice);

        return $invoice;
    }

    private function writeTermLines(Invoice $invoice, Plan $plan, CarbonImmutable $termStart, CarbonImmutable $termEnd, ?int $seats): void
    {
        $replacements = [
            'plan' => $plan->displayName(),
            'from' => $termStart->translatedFormat('d/m/Y'),
            'to' => $termEnd->translatedFormat('d/m/Y'),
        ];

        $order = 0;

        if (! $plan->isSeatBased() || (float) $plan->price_ht > 0) {
            $invoice->lines()->create([
                'description' => __('usage-billing::billing.lines.term', $replacements),
                'quantity' => 1,
                'unit_price_ht' => (float) $plan->price_ht,
                'amount_ht' => (float) $plan->price_ht,
                'sort_order' => $order++,
            ]);
        }

        if ($seats !== null) {
            $invoice->lines()->create([
                'description' => __('usage-billing::billing.lines.term_seats', [...$replacements, 'count' => $seats]),
                'quantity' => $seats,
                'unit_price_ht' => (float) $plan->seat_price_ht,
                'amount_ht' => round($seats * (float) $plan->seat_price_ht, 2),
                'sort_order' => $order,
            ]);
        }
    }

    /**
     * Where the next term begins: right after the current one for a renewal,
     * the day after a free trial ends, today for a first purchase.
     */
    public function nextTermStart(Subscription $subscription): CarbonImmutable
    {
        $trialEndsAt = $this->unpaidTrialEnd($subscription);

        if ($trialEndsAt !== null) {
            return $trialEndsAt->addDay()->startOfDay();
        }

        $endsAt = $subscription->ends_at;

        return $endsAt !== null && $endsAt->isFuture()
            ? $endsAt->addDay()->startOfDay()
            : CarbonImmutable::now()->startOfDay();
    }

    /**
     * Share of the term still to run, used to prorate an upgrade. Falls back to
     * a whole term when the subscription has no dates yet.
     */
    public function remainingTermRatio(Subscription $subscription): float
    {
        $startsAt = $subscription->starts_at;
        $endsAt = $subscription->ends_at;

        if ($startsAt === null || $endsAt === null || $endsAt->isPast()) {
            return 1.0;
        }

        $total = max(1, $startsAt->diffInDays($endsAt));
        $remaining = max(0, now()->diffInDays($endsAt, false));

        return min(1.0, $remaining / $total);
    }

    /** @deprecated Matches within one month only; use openTermInvoice(). */
    public function outstandingTermInvoice(Subscription $subscription, string $period): ?Invoice
    {
        return UsageBilling::query('invoice')
            ->where('subscription_id', $subscription->getKey())
            ->where('period', $period)
            ->awaitingPayment()
            ->first();
    }

    private function recalculateTotals(Invoice $invoice): void
    {
        $subtotal = round((float) $invoice->lines()->sum('amount_ht'), 2);
        $tva = round($subtotal * (float) $invoice->tva_rate / 100, 2);

        $invoice->forceFill([
            'subtotal_ht' => $subtotal,
            'tva_amount' => $tva,
            'total_ttc' => round($subtotal + $tva, 2),
        ])->save();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function usageSnapshot(Subscription $subscription): array
    {
        return $this->synchronizer
            ->summarise($subscription, Period::current(), persist: false)
            ->map(fn ($summary): array => $summary->toArray())
            ->values()
            ->all();
    }

    /**
     * @return array<string, string|null>
     */
    private function buyerSnapshot(Model $subscriber): array
    {
        if ($subscriber instanceof BillingParty) {
            return [
                'buyer_name' => $subscriber->billingName(),
                'buyer_ice' => $subscriber->billingIce(),
                'buyer_identifiant_fiscal' => $subscriber->billingIdentifiantFiscal(),
                'buyer_address' => $subscriber->billingAddress(),
                'buyer_email' => $subscriber->billingEmail(),
            ];
        }

        return [
            'buyer_name' => (string) ($subscriber->getAttribute('name') ?? class_basename($subscriber)),
            'buyer_ice' => null,
            'buyer_identifiant_fiscal' => null,
            'buyer_address' => null,
            'buyer_email' => $subscriber->getAttribute('email'),
        ];
    }
}

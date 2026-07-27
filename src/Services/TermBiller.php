<?php

declare(strict_types=1);

namespace HoceineEl\UsageBilling\Services;

use Carbon\CarbonImmutable;
use HoceineEl\UsageBilling\Contracts\BillingParty;
use HoceineEl\UsageBilling\Enums\InvoiceStatus;
use HoceineEl\UsageBilling\Enums\SubscriptionEventType;
use HoceineEl\UsageBilling\Models\Invoice;
use HoceineEl\UsageBilling\Models\Plan;
use HoceineEl\UsageBilling\Models\Subscription;
use HoceineEl\UsageBilling\Support\Period;
use HoceineEl\UsageBilling\UsageBilling;
use Illuminate\Database\Eloquent\Model;
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
     * Invoice the next term. Returns the existing unpaid term invoice when one
     * is already out, so a renewal run cannot bill the same term twice.
     */
    public function issueTermInvoice(Subscription $subscription, ?CarbonImmutable $termStart = null): ?Invoice
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
        $period = $termStart->format('Y-m');

        $existing = $this->outstandingTermInvoice($subscription, $period);

        if ($existing instanceof Invoice) {
            return $existing;
        }

        return DB::transaction(function () use ($subscription, $subscriber, $plan, $termStart, $termEnd, $period): Invoice {
            /** @var Invoice $invoice */
            $invoice = UsageBilling::query('invoice')->create([
                ...$this->buyerSnapshot($subscriber),
                'subscriber_type' => $subscriber->getMorphClass(),
                'subscriber_id' => $subscriber->getKey(),
                'subscription_id' => $subscription->getKey(),
                'period' => $period,
                'status' => InvoiceStatus::Draft,
                'currency' => $plan->currency ?? UsageBilling::currency(),
                'tva_rate' => $plan->tva_rate,
                'usage_snapshot' => $this->usageSnapshot($subscription),
            ]);

            $invoice->lines()->create([
                'description' => __('usage-billing::billing.lines.term', [
                    'plan' => $plan->displayName(),
                    'from' => $termStart->translatedFormat('d/m/Y'),
                    'to' => $termEnd->translatedFormat('d/m/Y'),
                ]),
                'quantity' => 1,
                'unit_price_ht' => (float) $plan->price_ht,
                'amount_ht' => (float) $plan->price_ht,
                'sort_order' => 0,
            ]);

            $this->recalculateTotals($invoice);

            $invoice->forceFill([
                'number' => $this->numbers->next(),
                'status' => InvoiceStatus::Issued,
                'issued_at' => now(),
                'due_at' => now()->addDays((int) $plan->payment_term_days),
                'notes' => json_encode([
                    'term_starts_at' => $termStart->toDateString(),
                    'term_ends_at' => $termEnd->toDateString(),
                ]),
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
    }

    /**
     * Bill the difference when a cabinet outgrows its plan mid-term, charged
     * only for the months it has left rather than a fresh full year.
     */
    public function issueUpgradeInvoice(Subscription $subscription, Plan $target): ?Invoice
    {
        $current = $subscription->plan;
        $subscriber = $subscription->subscriber;

        if (! $current instanceof Plan || ! $subscriber instanceof Model) {
            return null;
        }

        $difference = (float) $target->price_ht - (float) $current->price_ht;

        if ($difference <= 0) {
            return null;
        }

        $amount = round($difference * $this->remainingTermRatio($subscription), 2);

        if ($amount <= 0) {
            return null;
        }

        return DB::transaction(function () use ($subscription, $subscriber, $current, $target, $amount): Invoice {
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
                'notes' => json_encode(['upgrade_to_plan_id' => $target->getKey()]),
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
    }

    /**
     * Where the next term begins: right after the current one for a renewal,
     * today for a first purchase.
     */
    public function nextTermStart(Subscription $subscription): CarbonImmutable
    {
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

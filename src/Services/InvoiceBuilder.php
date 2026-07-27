<?php

declare(strict_types=1);

namespace HoceineEl\UsageBilling\Services;

use HoceineEl\UsageBilling\Contracts\BillingParty;
use HoceineEl\UsageBilling\Data\ModuleUsageSummary;
use HoceineEl\UsageBilling\Enums\InvoiceStatus;
use HoceineEl\UsageBilling\Enums\SubscriptionEventType;
use HoceineEl\UsageBilling\Events\InvoiceIssued;
use HoceineEl\UsageBilling\Models\Invoice;
use HoceineEl\UsageBilling\Models\Subscription;
use HoceineEl\UsageBilling\Support\Period;
use HoceineEl\UsageBilling\UsageBilling;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Turns a closed period into a facture.
 *
 * Building is idempotent on (subscription, period): re-running a close finds
 * the existing invoice and leaves it alone, so a failed batch can simply be
 * run again.
 */
class InvoiceBuilder
{
    public function __construct(
        private readonly UsageSynchronizer $synchronizer,
        private readonly InvoiceNumberGenerator $numbers,
    ) {}

    public function build(Subscription $subscription, Period $period, bool $syncUsage = true): ?Invoice
    {
        $existing = $this->existing($subscription, $period);

        if ($existing instanceof Invoice) {
            return $existing;
        }

        $subscriber = $subscription->subscriber;

        if (! $subscriber instanceof Model) {
            return null;
        }

        $summaries = $this->synchronizer->summarise($subscription, $period, $syncUsage);
        $basePrice = (float) $subscription->plan->price_ht;
        $billable = $summaries->filter(fn (ModuleUsageSummary $summary): bool => $summary->isBillable());

        if ($basePrice <= 0.0 && $billable->isEmpty()) {
            return null;
        }

        return DB::transaction(function () use ($subscription, $subscriber, $period, $summaries, $billable, $basePrice): Invoice {
            /** @var Invoice $invoice */
            $invoice = UsageBilling::query('invoice')->create([
                ...$this->buyerSnapshot($subscriber),
                'subscriber_type' => $subscriber->getMorphClass(),
                'subscriber_id' => $subscriber->getKey(),
                'subscription_id' => $subscription->getKey(),
                'period' => $period->key,
                'status' => InvoiceStatus::Draft,
                'currency' => $subscription->plan->currency ?? UsageBilling::currency(),
                'tva_rate' => $subscription->plan->tva_rate,
                'usage_snapshot' => $summaries
                    ->map(fn (ModuleUsageSummary $summary): array => $summary->toArray())
                    ->values()
                    ->all(),
            ]);

            $this->writeLines($invoice, $subscription, $period, $basePrice, $billable);
            $this->recalculateTotals($invoice);

            return $invoice;
        });
    }

    /**
     * Assign the number, stamp the dates and move the invoice out of draft.
     * Kept separate from building so a dry run can produce a draft without
     * consuming a number from the yearly sequence.
     */
    public function issue(Invoice $invoice): Invoice
    {
        if ($invoice->status !== InvoiceStatus::Draft) {
            return $invoice;
        }

        $termDays = (int) ($invoice->subscription?->plan?->payment_term_days
            ?? config('usage-billing.invoicing.payment_term_days', 30));

        $invoice->forceFill([
            'number' => $invoice->number ?? $this->numbers->next(),
            'status' => InvoiceStatus::Issued,
            'issued_at' => now(),
            'due_at' => now()->addDays($termDays),
        ])->save();

        $invoice->subscription?->events()->create([
            'type' => SubscriptionEventType::InvoiceIssued,
            'to_value' => $invoice->number,
            'meta' => [
                'period' => $invoice->period,
                'total_ttc' => (float) $invoice->total_ttc,
            ],
        ]);

        InvoiceIssued::dispatch($invoice);

        return $invoice;
    }

    public function existing(Subscription $subscription, Period $period): ?Invoice
    {
        return UsageBilling::query('invoice')
            ->where('subscription_id', $subscription->getKey())
            ->where('period', $period->key)
            ->first();
    }

    public function recalculateTotals(Invoice $invoice): Invoice
    {
        $subtotal = round((float) $invoice->lines()->sum('amount_ht'), 2);
        $tva = round($subtotal * (float) $invoice->tva_rate / 100, 2);

        $invoice->forceFill([
            'subtotal_ht' => $subtotal,
            'tva_amount' => $tva,
            'total_ttc' => round($subtotal + $tva, 2),
        ])->save();

        return $invoice;
    }

    /**
     * @param  Collection<string, ModuleUsageSummary>  $billable
     */
    private function writeLines(
        Invoice $invoice,
        Subscription $subscription,
        Period $period,
        float $basePrice,
        Collection $billable,
    ): void {
        $order = 0;

        if ($basePrice > 0.0) {
            $invoice->lines()->create([
                'description' => __('usage-billing::billing.lines.base_plan', [
                    'plan' => $subscription->plan->displayName(),
                    'period' => $period->label(),
                ]),
                'quantity' => 1,
                'unit_price_ht' => $basePrice,
                'amount_ht' => $basePrice,
                'sort_order' => $order++,
            ]);
        }

        $moduleIds = UsageBilling::query('module')
            ->whereIn('key', $billable->keys()->all())
            ->pluck('id', 'key');

        foreach ($billable as $key => $summary) {
            $invoice->lines()->create([
                'module_id' => $moduleIds->get($key),
                'description' => __('usage-billing::billing.lines.overage', [
                    'module' => $summary->moduleLabel,
                    'unit' => $summary->unitLabel,
                    'allowance' => $summary->allowance,
                ]),
                'quantity' => $summary->overageQuantity(),
                'unit_price_ht' => $summary->unitPriceHt,
                'amount_ht' => $summary->overageAmountHt(),
                'sort_order' => $order++,
            ]);
        }
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

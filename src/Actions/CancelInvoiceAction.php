<?php

declare(strict_types=1);

namespace HoceineEl\UsageBilling\Actions;

use HoceineEl\UsageBilling\Enums\InvoiceStatus;
use HoceineEl\UsageBilling\Enums\PaymentStatus;
use HoceineEl\UsageBilling\Enums\SubscriptionEventType;
use HoceineEl\UsageBilling\Enums\SubscriptionStatus;
use HoceineEl\UsageBilling\Models\Invoice;
use HoceineEl\UsageBilling\Models\Subscription;
use HoceineEl\UsageBilling\Services\SubscriptionManager;
use HoceineEl\UsageBilling\Services\TermBiller;
use HoceineEl\UsageBilling\UsageBilling;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Annuls a facture issued in error. It keeps its number, so the sequence stays
 * unbroken, and the subscription is brought back in line with what the
 * subscriber really owes.
 */
class CancelInvoiceAction
{
    public function __construct(private readonly SubscriptionManager $subscriptions) {}

    /**
     * Money already received or declared has to be settled first: annulling
     * would leave it attached to a facture that no longer asks for it.
     */
    public function canCancel(Invoice $invoice): bool
    {
        return $invoice->status->awaitsPayment()
            && (float) $invoice->amount_paid <= 0.0
            && ! $invoice->payments()->whereIn('status', [PaymentStatus::Pending, PaymentStatus::Validated])->exists();
    }

    public function execute(Invoice $invoice, string $reason, ?Model $actor = null): bool
    {
        return DB::transaction(function () use ($invoice, $reason, $actor): bool {
            /** @var Invoice|null $invoice */
            $invoice = UsageBilling::query('invoice')->lockForUpdate()->find($invoice->getKey());

            if ($invoice === null || ! $this->canCancel($invoice)) {
                return false;
            }

            $intent = json_decode((string) $invoice->notes, true);

            $invoice->forceFill([
                'status' => InvoiceStatus::Cancelled,
                'notes' => json_encode([
                    ...(is_array($intent) ? $intent : []),
                    'cancellation' => [
                        'reason' => $reason,
                        'cancelled_at' => now()->toIso8601String(),
                        'cancelled_by' => $actor?->getKey(),
                    ],
                ], JSON_THROW_ON_ERROR),
            ])->save();

            $subscription = $invoice->subscription;

            if ($subscription === null) {
                return true;
            }

            $subscription->events()->create([
                'type' => SubscriptionEventType::InvoiceCancelled,
                'to_value' => $invoice->number,
                'actor_type' => $actor?->getMorphClass(),
                'actor_id' => $actor?->getKey(),
                'meta' => ['reason' => $reason],
            ]);

            $this->settle($subscription, $actor);

            return true;
        });
    }

    /**
     * A paid term that only waited on the annulled facture opens, and arrears
     * with nothing left to pay are lifted.
     */
    private function settle(Subscription $subscription, ?Model $actor): void
    {
        if ($subscription->invoices()->awaitingPayment()->exists()) {
            return;
        }

        $paidTerm = $subscription->invoices()
            ->where('status', InvoiceStatus::Paid)
            ->latest('paid_at')
            ->get()
            ->map(fn (Invoice $invoice): ?array => TermBiller::termOf($invoice))
            ->filter()
            ->first();

        if ($paidTerm !== null && ($subscription->ends_at === null || $subscription->ends_at->lessThan($paidTerm['ends_at']))) {
            $this->subscriptions->activateTerm($subscription, $paidTerm['starts_at'], $paidTerm['ends_at'], $actor);

            return;
        }

        if ($subscription->status !== SubscriptionStatus::PastDue || $subscription->ends_at?->isPast()) {
            return;
        }

        $status = match (true) {
            $subscription->ends_at !== null => SubscriptionStatus::Active,
            $subscription->trial_ends_at?->isFuture() === true => SubscriptionStatus::Trialing,
            default => SubscriptionStatus::PendingPayment,
        };

        $this->subscriptions->transitionTo($subscription, $status, $actor, ['grace_ends_at' => null]);
    }
}

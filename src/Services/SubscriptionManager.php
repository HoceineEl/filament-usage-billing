<?php

declare(strict_types=1);

namespace HoceineEl\UsageBilling\Services;

use Carbon\CarbonImmutable;
use HoceineEl\UsageBilling\Data\GatewayPayment;
use HoceineEl\UsageBilling\Enums\InvoiceStatus;
use HoceineEl\UsageBilling\Enums\PaymentStatus;
use HoceineEl\UsageBilling\Enums\SubscriptionEventType;
use HoceineEl\UsageBilling\Enums\SubscriptionStatus;
use HoceineEl\UsageBilling\Events\PaymentValidated;
use HoceineEl\UsageBilling\Events\SubscriptionStatusChanged;
use HoceineEl\UsageBilling\Models\Invoice;
use HoceineEl\UsageBilling\Models\Payment;
use HoceineEl\UsageBilling\Models\Plan;
use HoceineEl\UsageBilling\Models\Subscription;
use HoceineEl\UsageBilling\UsageBilling;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Owns every subscription state transition, so status, grace window and audit
 * trail can never disagree with each other.
 */
class SubscriptionManager
{
    /**
     * Terms are prepaid, so a subscription without a trial starts life awaiting
     * payment rather than active. `activateTerm()` is what turns it on, and
     * that only happens once a payment has been validated.
     */
    public function start(Model $subscriber, Plan $plan, bool $withTrial = true, ?int $seats = null): Subscription
    {
        $trialEndsAt = $withTrial && $plan->trial_days > 0
            ? now()->addDays($plan->trial_days)
            : null;

        /** @var Subscription $subscription */
        $subscription = UsageBilling::query('subscription')->create([
            'subscriber_type' => $subscriber->getMorphClass(),
            'subscriber_id' => $subscriber->getKey(),
            'plan_id' => $plan->getKey(),
            'status' => $trialEndsAt !== null ? SubscriptionStatus::Trialing : SubscriptionStatus::PendingPayment,
            'seats' => $plan->isSeatBased() ? $plan->billableSeats($seats) : null,
            'starts_at' => now(),
            'trial_ends_at' => $trialEndsAt,
        ]);

        $subscription->events()->create([
            'type' => SubscriptionEventType::Created,
            'to_value' => $plan->slug,
        ]);

        return $subscription;
    }

    public function changePlan(Subscription $subscription, Plan $plan, ?Model $actor = null): Subscription
    {
        $previous = $subscription->plan;

        if ($previous?->getKey() === $plan->getKey()) {
            return $subscription;
        }

        $subscription->forceFill(['plan_id' => $plan->getKey()])->save();

        $subscription->events()->create([
            'type' => SubscriptionEventType::PlanChanged,
            'from_value' => $previous?->slug,
            'to_value' => $plan->slug,
            'actor_type' => $actor?->getMorphClass(),
            'actor_id' => $actor?->getKey(),
        ]);

        // Allowances belong to the plan, so the gate must stop answering from
        // the old one the moment the plan changes.
        $subscription->unsetRelation('plan');

        return $subscription;
    }

    public function transitionTo(
        Subscription $subscription,
        SubscriptionStatus $status,
        ?Model $actor = null,
        array $attributes = [],
    ): Subscription {
        $from = $subscription->status;

        if ($from === $status && $attributes === []) {
            return $subscription;
        }

        $subscription->forceFill([...$attributes, 'status' => $status])->save();

        $subscription->events()->create([
            'type' => SubscriptionEventType::StatusChanged,
            'from_value' => $from->value,
            'to_value' => $status->value,
            'actor_type' => $actor?->getMorphClass(),
            'actor_id' => $actor?->getKey(),
        ]);

        SubscriptionStatusChanged::dispatch($subscription, $from, $status);

        return $subscription;
    }

    public function cancel(Subscription $subscription, ?Model $actor = null): Subscription
    {
        return $this->transitionTo($subscription, SubscriptionStatus::Cancelled, $actor, [
            'cancelled_at' => now(),
            'ends_at' => $subscription->ends_at ?? now(),
        ]);
    }

    /**
     * Move a subscription into arrears because an invoice went unpaid, opening
     * the plan's grace window before access is withdrawn.
     */
    public function markPastDue(Subscription $subscription, Invoice $invoice): Subscription
    {
        if ($subscription->status === SubscriptionStatus::PastDue) {
            return $subscription;
        }

        $graceDays = (int) ($subscription->plan?->grace_days ?? 0);
        $graceEndsAt = ($invoice->due_at ?? now())->addDays($graceDays);

        return $this->transitionTo($subscription, SubscriptionStatus::PastDue, attributes: [
            'grace_ends_at' => $graceEndsAt,
        ]);
    }

    /**
     * Record an offline payment the subscriber declared. It counts for nothing
     * until an operator validates it against the bank.
     */
    public function declarePayment(Invoice $invoice, array $attributes, ?Model $submittedBy = null): Payment
    {
        /** @var Payment $payment */
        $payment = $invoice->payments()->create([
            ...$attributes,
            'status' => PaymentStatus::Pending,
            'currency' => $attributes['currency'] ?? $invoice->currency,
            'submitted_by_type' => $submittedBy?->getMorphClass(),
            'submitted_by_id' => $submittedBy?->getKey(),
        ]);

        return $payment;
    }

    /**
     * Record a payment a gateway has confirmed. The gateway already saw the
     * money move, so it is validated at once. A redelivered webhook finds the
     * payment by its reference and changes nothing.
     */
    public function recordGatewayPayment(Invoice $invoice, GatewayPayment $confirmed, string $gateway): Payment
    {
        /** @var Payment|null $existing */
        $existing = $invoice->payments()
            ->where('method', $confirmed->method)
            ->where('reference', $confirmed->reference)
            ->first();

        if ($existing instanceof Payment) {
            return $existing;
        }

        return $this->validatePayment($this->declarePayment($invoice, [
            'method' => $confirmed->method,
            'amount' => $confirmed->amount,
            'reference' => $confirmed->reference,
            'paid_at' => $confirmed->paidAt ?? now(),
            'notes' => "gateway:{$gateway}",
        ]));
    }

    public function validatePayment(Payment $payment, ?Model $actor = null): Payment
    {
        DB::transaction(function () use ($payment, $actor): void {
            $payment->forceFill([
                'status' => PaymentStatus::Validated,
                'validated_at' => now(),
                'validated_by_type' => $actor?->getMorphClass(),
                'validated_by_id' => $actor?->getKey(),
                'paid_at' => $payment->paid_at ?? now(),
            ])->save();

            $invoice = $payment->invoice;
            $invoice->recalculatePayments();

            $subscription = $invoice->subscription;

            if ($subscription === null) {
                return;
            }

            $subscription->events()->create([
                'type' => SubscriptionEventType::PaymentValidated,
                'to_value' => (string) $payment->amount,
                'actor_type' => $actor?->getMorphClass(),
                'actor_id' => $actor?->getKey(),
                'meta' => ['invoice' => $invoice->number, 'period' => $invoice->period],
            ]);

            // Only act once nothing else is outstanding: settling the newest
            // facture while an older one is still unpaid is not payment.
            $stillOwing = $subscription->invoices()
                ->whereIn('status', [InvoiceStatus::Issued, InvoiceStatus::PartiallyPaid, InvoiceStatus::Overdue])
                ->exists();

            if ($stillOwing || ! $invoice->isFullyPaid()) {
                return;
            }

            $this->applyPaidInvoice($subscription, $invoice, $actor);
        });

        PaymentValidated::dispatch($payment);

        return $payment;
    }

    /**
     * Turn a settled invoice into access.
     *
     * A term invoice starts or extends the paid term; an upgrade invoice swaps
     * the plan in. Either way the subscriber gets what it just paid for, and
     * not before.
     */
    protected function applyPaidInvoice(Subscription $subscription, Invoice $invoice, ?Model $actor = null): void
    {
        $intent = $this->invoiceIntent($invoice);

        if (isset($intent['upgrade_to_plan_id'])) {
            $target = UsageBilling::query('plan')->find($intent['upgrade_to_plan_id']);

            if ($target instanceof Plan) {
                $this->changePlan($subscription, $target, $actor);

                if (isset($intent['seats'])) {
                    $this->setSeats($subscription, (int) $intent['seats'], $actor);
                }
            }

            return;
        }

        if (isset($intent['add_seats'])) {
            $this->setSeats($subscription, (int) $subscription->seats + (int) $intent['add_seats'], $actor);

            return;
        }

        if (isset($intent['term_starts_at'], $intent['term_ends_at'])) {
            $this->activateTerm(
                $subscription,
                CarbonImmutable::parse($intent['term_starts_at'])->startOfDay(),
                CarbonImmutable::parse($intent['term_ends_at'])->endOfDay(),
                $actor,
                isset($intent['seats']) ? (int) $intent['seats'] : null,
            );

            return;
        }

        if ($subscription->status === SubscriptionStatus::PastDue) {
            $this->transitionTo($subscription, SubscriptionStatus::Active, $actor, [
                'grace_ends_at' => null,
            ]);
        }
    }

    /**
     * Open a paid term. `starts_at` is only moved for a first activation — a
     * renewal keeps the original start so the subscription's history stays
     * readable.
     */
    public function activateTerm(
        Subscription $subscription,
        CarbonImmutable $startsAt,
        CarbonImmutable $endsAt,
        ?Model $actor = null,
        ?int $seats = null,
    ): Subscription {
        $isFirstTerm = $subscription->ends_at === null;

        if ($seats !== null) {
            $this->setSeats($subscription, $seats, $actor);
        }

        return $this->transitionTo($subscription, SubscriptionStatus::Active, $actor, [
            'starts_at' => $isFirstTerm ? $startsAt : $subscription->starts_at,
            'ends_at' => $endsAt,
            'trial_ends_at' => null,
            'grace_ends_at' => null,
            'renewal_seats' => null,
        ]);
    }

    /**
     * Seats paid for. Allowances follow at once, since they are resolved from
     * the subscription on every check.
     */
    public function setSeats(Subscription $subscription, int $seats, ?Model $actor = null): Subscription
    {
        $from = $subscription->seats;

        if ($from === $seats) {
            return $subscription;
        }

        $subscription->forceFill(['seats' => $seats])->save();

        $subscription->events()->create([
            'type' => SubscriptionEventType::SeatsChanged,
            'from_value' => $from === null ? null : (string) $from,
            'to_value' => (string) $seats,
            'actor_type' => $actor?->getMorphClass(),
            'actor_id' => $actor?->getKey(),
        ]);

        return $subscription;
    }

    /**
     * Fewer seats from the next term. A paid term is never refunded, so a
     * reduction only changes what the renewal facture will bill.
     */
    public function scheduleRenewalSeats(Subscription $subscription, int $seats, ?Model $actor = null): Subscription
    {
        $plan = $subscription->plan;
        $seats = $plan instanceof Plan ? $plan->billableSeats($seats) : max(1, $seats);

        $subscription->forceFill([
            'renewal_seats' => $seats === $subscription->seats ? null : $seats,
        ])->save();

        $subscription->events()->create([
            'type' => SubscriptionEventType::SeatsChanged,
            'from_value' => $subscription->seats === null ? null : (string) $subscription->seats,
            'to_value' => (string) $seats,
            'actor_type' => $actor?->getMorphClass(),
            'actor_id' => $actor?->getKey(),
            'meta' => ['applies' => 'renewal'],
        ]);

        return $subscription;
    }

    /**
     * @return array<string, mixed>
     */
    protected function invoiceIntent(Invoice $invoice): array
    {
        $decoded = json_decode((string) $invoice->notes, true);

        return is_array($decoded) ? $decoded : [];
    }

    public function rejectPayment(Payment $payment, string $reason, ?Model $actor = null): Payment
    {
        $payment->forceFill([
            'status' => PaymentStatus::Rejected,
            'rejection_reason' => $reason,
            'validated_at' => now(),
            'validated_by_type' => $actor?->getMorphClass(),
            'validated_by_id' => $actor?->getKey(),
        ])->save();

        $payment->invoice->recalculatePayments();

        return $payment;
    }
}

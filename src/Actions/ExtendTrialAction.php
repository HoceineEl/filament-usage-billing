<?php

declare(strict_types=1);

namespace HoceineEl\UsageBilling\Actions;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use HoceineEl\UsageBilling\Enums\SubscriptionStatus;
use HoceineEl\UsageBilling\Models\Subscription;
use HoceineEl\UsageBilling\Services\SubscriptionManager;
use HoceineEl\UsageBilling\Services\TermBiller;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * More trial time for a subscriber that has not bought a term yet. The open
 * trial facture moves with it, so it cannot fall overdue inside the trial.
 */
class ExtendTrialAction
{
    public function __construct(
        private readonly SubscriptionManager $subscriptions,
        private readonly TermBiller $termBiller,
    ) {}

    public function canExtend(Subscription $subscription): bool
    {
        return $subscription->ends_at === null
            && in_array($subscription->status, [
                SubscriptionStatus::Trialing,
                SubscriptionStatus::PastDue,
                SubscriptionStatus::PendingPayment,
            ], true);
    }

    public function execute(Subscription $subscription, CarbonInterface|string $trialEndsAt, ?Model $actor = null): bool
    {
        if (! $this->canExtend($subscription)) {
            return false;
        }

        $trialEndsAt = CarbonImmutable::parse($trialEndsAt);

        DB::transaction(function () use ($subscription, $trialEndsAt, $actor): void {
            $this->subscriptions->transitionTo($subscription, SubscriptionStatus::Trialing, $actor, [
                'trial_ends_at' => $trialEndsAt,
                'grace_ends_at' => null,
            ]);

            $invoice = $this->termBiller->openTermInvoice($subscription);

            if ($invoice !== null && $invoice->due_at?->isBefore($trialEndsAt)) {
                $invoice->forceFill(['due_at' => $trialEndsAt])->save();
                $invoice->recalculatePayments();
            }
        });

        return true;
    }
}

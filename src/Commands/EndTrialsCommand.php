<?php

declare(strict_types=1);

namespace HoceineEl\UsageBilling\Commands;

use Carbon\CarbonImmutable;
use HoceineEl\UsageBilling\Enums\SubscriptionStatus;
use HoceineEl\UsageBilling\Models\Subscription;
use HoceineEl\UsageBilling\Services\SubscriptionManager;
use HoceineEl\UsageBilling\UsageBilling;
use Illuminate\Console\Command;

/**
 * Ends trials on the day they run out.
 *
 * Without this a trial keeps granting access until its facture falls overdue.
 * An unpaid trial moves into the plan's grace window instead, counted from the
 * trial end or the facture's due date, whichever is later.
 */
class EndTrialsCommand extends Command
{
    protected $signature = 'usage-billing:end-trials';

    protected $description = 'Move trials that ran out unpaid into their grace window';

    public function handle(SubscriptionManager $subscriptions): int
    {
        $expired = UsageBilling::query('subscription')
            ->with('plan')
            ->where('status', SubscriptionStatus::Trialing)
            ->whereNull('ends_at')
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<=', now())
            ->get();

        foreach ($expired as $subscription) {
            /** @var Subscription $subscription */
            $subscriptions->transitionTo($subscription, SubscriptionStatus::PastDue, attributes: [
                'grace_ends_at' => $this->graceStart($subscription)->addDays((int) ($subscription->plan?->grace_days ?? 0)),
            ]);
        }

        $this->components->info("{$expired->count()} trial(s) ended unpaid.");

        return self::SUCCESS;
    }

    /**
     * Never before the facture's own due date: a subscriber promised a payment
     * term keeps it even when the trial ends first.
     */
    private function graceStart(Subscription $subscription): CarbonImmutable
    {
        $trialEndsAt = $subscription->trial_ends_at ?? CarbonImmutable::now();
        $dueAt = $subscription->invoices()->awaitingPayment()->oldest('due_at')->first()?->due_at;

        return $dueAt instanceof CarbonImmutable && $dueAt->isAfter($trialEndsAt) ? $dueAt : $trialEndsAt;
    }
}

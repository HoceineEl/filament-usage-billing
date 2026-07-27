<?php

declare(strict_types=1);

namespace HoceineEl\UsageBilling\Commands;

use HoceineEl\UsageBilling\Enums\SubscriptionStatus;
use HoceineEl\UsageBilling\Models\Subscription;
use HoceineEl\UsageBilling\Services\SubscriptionManager;
use HoceineEl\UsageBilling\UsageBilling;
use Illuminate\Console\Command;

/**
 * Closes out terms nobody renewed.
 *
 * A term that has run out first becomes PastDue for the plan's grace window —
 * time to pay a renewal already invoiced — and only then expires.
 */
class ExpireSubscriptionsCommand extends Command
{
    protected $signature = 'usage-billing:expire-subscriptions';

    protected $description = 'Move ended terms into their grace window, then expire the ones nobody renewed';

    public function handle(SubscriptionManager $manager): int
    {
        $graced = 0;
        $expired = 0;

        $subscriptions = UsageBilling::query('subscription')
            ->with('plan')
            ->whereIn('status', [
                SubscriptionStatus::Active,
                SubscriptionStatus::Trialing,
                SubscriptionStatus::PastDue,
            ])
            ->whereNotNull('ends_at')
            ->where('ends_at', '<', now())
            ->get();

        foreach ($subscriptions as $subscription) {
            /** @var Subscription $subscription */
            if ($subscription->grace_ends_at === null) {
                $graceDays = (int) ($subscription->plan?->grace_days ?? 0);

                $manager->transitionTo($subscription, SubscriptionStatus::PastDue, attributes: [
                    'grace_ends_at' => $subscription->ends_at->addDays($graceDays),
                ]);

                $graced++;

                continue;
            }

            if ($subscription->grace_ends_at->isPast()) {
                $manager->transitionTo($subscription, SubscriptionStatus::Expired);
                $expired++;
            }
        }

        $this->components->info("{$graced} moved into grace, {$expired} expired.");

        return self::SUCCESS;
    }
}

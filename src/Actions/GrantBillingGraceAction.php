<?php

declare(strict_types=1);

namespace HoceineEl\UsageBilling\Actions;

use HoceineEl\UsageBilling\Contracts\Subscribable;
use HoceineEl\UsageBilling\Enums\SubscriptionStatus;
use HoceineEl\UsageBilling\Models\Subscription;
use HoceineEl\UsageBilling\Services\SubscriptionManager;
use Illuminate\Database\Eloquent\Model;

/**
 * The operator's "unlock": reopens a locked subscription for a grace window
 * while its payment is sorted out, without touching what it owes.
 */
class GrantBillingGraceAction
{
    public function __construct(private readonly SubscriptionManager $subscriptions) {}

    public function isLocked(Subscription $subscription): bool
    {
        $subscriber = $subscription->subscriber;

        if ($subscriber instanceof Subscribable && ! $subscriber->usageLimitsEnforced()) {
            return false;
        }

        return ! in_array($subscription->status, [SubscriptionStatus::Cancelled, SubscriptionStatus::Expired], true)
            && ! $subscription->isUsable();
    }

    public function execute(Subscription $subscription, ?Model $actor = null, ?int $days = null): bool
    {
        if (! $this->isLocked($subscription)) {
            return false;
        }

        $days ??= (int) config('usage-billing.grace.admin_days', 30);

        $this->subscriptions->transitionTo($subscription, SubscriptionStatus::PastDue, $actor, [
            'grace_ends_at' => now()->addDays($days),
        ]);

        return true;
    }
}

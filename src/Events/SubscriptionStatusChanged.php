<?php

declare(strict_types=1);

namespace HoceineEl\UsageBilling\Events;

use HoceineEl\UsageBilling\Enums\SubscriptionStatus;
use HoceineEl\UsageBilling\Models\Subscription;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SubscriptionStatusChanged
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Subscription $subscription,
        public readonly SubscriptionStatus $from,
        public readonly SubscriptionStatus $to,
    ) {}
}

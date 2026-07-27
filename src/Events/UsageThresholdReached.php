<?php

declare(strict_types=1);

namespace HoceineEl\UsageBilling\Events;

use HoceineEl\UsageBilling\Models\Subscription;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UsageThresholdReached
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Subscription $subscription,
        public readonly string $moduleKey,
        public readonly int $threshold,
        public readonly int $used,
        public readonly int $allowance,
    ) {}
}

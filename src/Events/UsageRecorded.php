<?php

declare(strict_types=1);

namespace HoceineEl\UsageBilling\Events;

use HoceineEl\UsageBilling\Data\UsageBucket;
use HoceineEl\UsageBilling\Models\Subscription;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UsageRecorded
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Subscription $subscription,
        public readonly string $moduleKey,
        public readonly int $quantity,
        public readonly UsageBucket $bucket,
        public readonly string $period,
    ) {}
}

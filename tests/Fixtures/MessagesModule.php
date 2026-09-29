<?php

declare(strict_types=1);

namespace HoceineEl\UsageBilling\Tests\Fixtures;

use Carbon\CarbonImmutable;
use HoceineEl\UsageBilling\Contracts\HasResetPeriod;
use HoceineEl\UsageBilling\Contracts\MeteredModule;
use HoceineEl\UsageBilling\Data\UsageBucket;
use HoceineEl\UsageBilling\Enums\ModuleType;
use HoceineEl\UsageBilling\Enums\ResetPeriod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * A metered module whose allowance refills every day.
 */
class MessagesModule implements HasResetPeriod, MeteredModule
{
    public static function key(): string
    {
        return 'messages';
    }

    public function label(): string
    {
        return 'Messages';
    }

    public function unitLabel(): string
    {
        return 'messages';
    }

    public function type(): ModuleType
    {
        return ModuleType::Metered;
    }

    public function resetPeriod(): ResetPeriod
    {
        return ResetPeriod::Day;
    }

    public function usage(Model $subscriber, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return collect([UsageBucket::unattributed(DB::table('messages')
            ->where('cabinet_id', $subscriber->getKey())
            ->whereBetween('sent_at', [$from, $to])
            ->count())]);
    }
}

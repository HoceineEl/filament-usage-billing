<?php

declare(strict_types=1);

namespace HoceineEl\UsageBilling\Tests\Fixtures;

use Carbon\CarbonImmutable;
use HoceineEl\UsageBilling\Contracts\MeteredModule;
use HoceineEl\UsageBilling\Data\UsageBucket;
use HoceineEl\UsageBilling\Enums\ModuleType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * A snapshot module: the number is a state at the end of the period, not a
 * count of events inside it.
 */
class SeatsModule implements MeteredModule
{
    public static function key(): string
    {
        return 'seats';
    }

    public function label(): string
    {
        return 'Active customers';
    }

    public function unitLabel(): string
    {
        return 'customers';
    }

    public function type(): ModuleType
    {
        return ModuleType::Snapshot;
    }

    public function usage(Model $subscriber, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        $count = Customer::query()
            ->where('cabinet_id', $subscriber->getKey())
            ->where('active', true)
            ->where('created_at', '<=', $to)
            ->count();

        return collect([UsageBucket::unattributed($count)]);
    }
}

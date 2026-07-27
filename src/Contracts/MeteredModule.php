<?php

declare(strict_types=1);

namespace HoceineEl\UsageBilling\Contracts;

use Carbon\CarbonImmutable;
use HoceineEl\UsageBilling\Data\UsageBucket;
use HoceineEl\UsageBilling\Enums\ModuleType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * A billable capability, implemented by the consuming application.
 *
 * `usage()` is the billing authority. Counters exist to answer allowance
 * questions quickly, but closing a period always recomputes them from this
 * method, so an implementation must be able to return the same numbers for a
 * past period as it did when that period was live.
 */
interface MeteredModule
{
    public static function key(): string;

    public function label(): string;

    /** Plural noun for the thing being counted, e.g. "documents". */
    public function unitLabel(): string;

    public function type(): ModuleType;

    /**
     * Usage for the period, broken down by whatever the application attributes
     * consumption to. Return a single unattributed bucket when no breakdown
     * applies.
     *
     * @return Collection<int, UsageBucket>
     */
    public function usage(Model $subscriber, CarbonImmutable $from, CarbonImmutable $to): Collection;
}

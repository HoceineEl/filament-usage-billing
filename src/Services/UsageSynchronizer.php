<?php

declare(strict_types=1);

namespace HoceineEl\UsageBilling\Services;

use HoceineEl\UsageBilling\Data\ModuleUsageSummary;
use HoceineEl\UsageBilling\Data\UsageBucket;
use HoceineEl\UsageBilling\Enums\ModuleType;
use HoceineEl\UsageBilling\Models\PlanModule;
use HoceineEl\UsageBilling\Models\Subscription;
use HoceineEl\UsageBilling\Support\Period;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Recomputes usage from the modules themselves.
 *
 * This is the authority the whole design rests on: whatever the counters say,
 * a period is closed against these numbers, so a retried job, a lost increment
 * or a deleted record all resolve to the truth held in the application's own
 * tables.
 */
class UsageSynchronizer
{
    public function __construct(
        private readonly ModuleRegistry $registry,
        private readonly UsageRecorder $recorder,
    ) {}

    /**
     * Authoritative usage for every module the plan carries, optionally
     * writing the result back over the counter cache.
     *
     * @return Collection<string, ModuleUsageSummary>
     */
    public function summarise(Subscription $subscription, Period $period, bool $persist = true): Collection
    {
        $subscriber = $subscription->subscriber;

        if (! $subscriber instanceof Model) {
            return collect();
        }

        $subscription->loadMissing('plan.planModules.module');

        return $subscription->plan
            ->planModules
            ->filter(fn (PlanModule $pricing): bool => $pricing->module?->is_active === true)
            ->mapWithKeys(function (PlanModule $pricing) use ($subscription, $subscriber, $period, $persist): array {
                $key = (string) $pricing->module->key;
                $buckets = $this->bucketsFor($key, $subscriber, $period);

                if ($persist) {
                    $this->recorder->replace($subscription, $key, $buckets, $period);
                }

                $module = $this->registry->get($key);

                return [$key => new ModuleUsageSummary(
                    moduleKey: $key,
                    moduleLabel: $module->label(),
                    unitLabel: $module->unitLabel(),
                    total: (int) $buckets->sum(fn (UsageBucket $bucket): int => $bucket->quantity),
                    allowance: $pricing->included_quantity,
                    unitPriceHt: $pricing->unit_price_ht,
                    ceiling: $pricing->hard_ceiling,
                    buckets: $buckets,
                )];
            });
    }

    /**
     * @return Collection<int, UsageBucket>
     */
    public function bucketsFor(string $moduleKey, Model $subscriber, Period $period): Collection
    {
        $module = $this->registry->get($moduleKey);

        $buckets = $module->usage($subscriber, $period->start, $period->end)
            ->filter(fn (UsageBucket $bucket): bool => $bucket->quantity > 0)
            ->values();

        // A snapshot module measures a state, not a stream, so several rows for
        // the same attribution would mean the same thing counted twice.
        return $module->type() === ModuleType::Snapshot
            ? $buckets->unique(fn (UsageBucket $bucket): string => $bucket->key())->values()
            : $buckets;
    }
}

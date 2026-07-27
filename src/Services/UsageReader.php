<?php

declare(strict_types=1);

namespace HoceineEl\UsageBilling\Services;

use HoceineEl\UsageBilling\Models\Subscription;
use HoceineEl\UsageBilling\Support\Period;
use HoceineEl\UsageBilling\UsageBilling;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;

/**
 * Reads cached counter totals.
 *
 * Split out from the recorder so the read path stays trivial: a gate check
 * happens on every upload and every message send, and it must not carry the
 * write path's transaction machinery.
 */
class UsageReader
{
    public function total(Subscription $subscription, string $moduleKey, ?Period $period = null): int
    {
        $period ??= Period::current();

        return (int) $this->cache()->remember(
            $this->cacheKey($subscription, $moduleKey, $period),
            (int) config('usage-billing.cache.ttl', 300),
            fn (): int => $this->totalFromDatabase($subscription, $moduleKey, $period),
        );
    }

    public function totalFromDatabase(Subscription $subscription, string $moduleKey, Period $period): int
    {
        return (int) UsageBilling::query('usage_counter')
            ->where('subscription_id', $subscription->getKey())
            ->where('period', $period->key)
            ->whereHas('module', fn ($query) => $query->where('key', $moduleKey))
            ->sum('quantity');
    }

    public function forget(Subscription $subscription, string $moduleKey, Period $period): void
    {
        $this->cache()->forget($this->cacheKey($subscription, $moduleKey, $period));
    }

    public function forgetSubscription(Subscription $subscription, Period $period): void
    {
        foreach (app(ModuleRegistry::class)->all()->keys() as $key) {
            $this->forget($subscription, $key, $period);
        }
    }

    private function cacheKey(Subscription $subscription, string $moduleKey, Period $period): string
    {
        $prefix = config('usage-billing.cache.prefix', 'usage-billing');

        return "{$prefix}:usage:{$subscription->getKey()}:{$moduleKey}:{$period->key}";
    }

    private function cache(): Repository
    {
        $store = config('usage-billing.cache.store');

        return $store === null ? Cache::store() : Cache::store($store);
    }
}

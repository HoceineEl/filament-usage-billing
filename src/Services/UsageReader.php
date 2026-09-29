<?php

declare(strict_types=1);

namespace HoceineEl\UsageBilling\Services;

use HoceineEl\UsageBilling\Data\UsageBucket;
use HoceineEl\UsageBilling\Enums\ResetPeriod;
use HoceineEl\UsageBilling\Models\Subscription;
use HoceineEl\UsageBilling\Support\Period;
use HoceineEl\UsageBilling\Support\UsageWindow;
use HoceineEl\UsageBilling\UsageBilling;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Database\Eloquent\Model;
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
            $this->cacheKey($subscription, $moduleKey, $period->key),
            (int) config('usage-billing.cache.ttl', 300),
            fn (): int => $this->totalFromDatabase($subscription, $moduleKey, $period),
        );
    }

    /**
     * Usage inside a window shorter than a month, read from the module itself
     * because the counters only hold monthly totals.
     */
    public function windowTotal(Subscription $subscription, Model $subscriber, string $moduleKey, UsageWindow $window): int
    {
        if ($window->resetPeriod === ResetPeriod::Month) {
            return $this->total($subscription, $moduleKey, Period::fromKey($window->key));
        }

        return (int) $this->cache()->remember(
            $this->cacheKey($subscription, $moduleKey, $window->key),
            (int) config('usage-billing.cache.ttl', 300),
            fn (): int => $this->windowTotalFromModule($subscriber, $moduleKey, $window),
        );
    }

    public function windowTotalFromModule(Model $subscriber, string $moduleKey, UsageWindow $window): int
    {
        return (int) app(UsageSynchronizer::class)
            ->bucketsBetween($moduleKey, $subscriber, $window->start, $window->end)
            ->sum(fn (UsageBucket $bucket): int => $bucket->quantity);
    }

    public function forgetWindow(Subscription $subscription, string $moduleKey, UsageWindow $window): void
    {
        $this->cache()->forget($this->cacheKey($subscription, $moduleKey, $window->key));
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
        $this->cache()->forget($this->cacheKey($subscription, $moduleKey, $period->key));
    }

    public function forgetSubscription(Subscription $subscription, Period $period): void
    {
        foreach (app(ModuleRegistry::class)->all()->keys() as $key) {
            $this->forget($subscription, $key, $period);
        }
    }

    private function cacheKey(Subscription $subscription, string $moduleKey, string $windowKey): string
    {
        $prefix = config('usage-billing.cache.prefix', 'usage-billing');

        return "{$prefix}:usage:{$subscription->getKey()}:{$moduleKey}:{$windowKey}";
    }

    private function cache(): Repository
    {
        $store = config('usage-billing.cache.store');

        return $store === null ? Cache::store() : Cache::store($store);
    }
}

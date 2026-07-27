<?php

declare(strict_types=1);

namespace HoceineEl\UsageBilling\Services;

use HoceineEl\UsageBilling\Data\UsageBucket;
use HoceineEl\UsageBilling\Enums\SubscriptionEventType;
use HoceineEl\UsageBilling\Events\UsageRecorded;
use HoceineEl\UsageBilling\Events\UsageThresholdReached;
use HoceineEl\UsageBilling\Models\Subscription;
use HoceineEl\UsageBilling\Support\Period;
use HoceineEl\UsageBilling\UsageBilling;
use Illuminate\Support\Facades\DB;

/**
 * Writes to the counter cache.
 *
 * The counters this maintains are never the billing authority — closing a
 * period recomputes them from the module queries — so this path optimises for
 * being cheap and non-blocking rather than exact.
 */
class UsageRecorder
{
    public function __construct(
        private readonly ModuleRegistry $registry,
        private readonly UsageReader $reader,
    ) {}

    public function record(
        Subscription $subscription,
        string $moduleKey,
        int $quantity = 1,
        ?UsageBucket $bucket = null,
        ?Period $period = null,
    ): void {
        if ($quantity === 0) {
            return;
        }

        $period ??= Period::current();
        $bucket ??= UsageBucket::unattributed($quantity);
        $moduleId = $this->registry->recordId($moduleKey);
        $table = UsageBilling::table('usage_counter');

        // Seeded at zero and incremented separately: the upsert must not carry
        // the quantity, or a first insert would apply it twice.
        DB::table($table)->upsert(
            [[
                'subscription_id' => $subscription->getKey(),
                'module_id' => $moduleId,
                'period' => $period->key,
                'attribution_key' => $bucket->key(),
                'attribution_type' => $bucket->attributionType,
                'attribution_id' => $bucket->attributionId,
                'attribution_label' => $bucket->attributionLabel,
                'quantity' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]],
            ['subscription_id', 'module_id', 'period', 'attribution_key'],
            ['attribution_label', 'updated_at'],
        );

        DB::table($table)
            ->where('subscription_id', $subscription->getKey())
            ->where('module_id', $moduleId)
            ->where('period', $period->key)
            ->where('attribution_key', $bucket->key())
            ->increment('quantity', $quantity, ['updated_at' => now()]);

        $this->reader->forget($subscription, $moduleKey, $period);

        UsageRecorded::dispatch($subscription, $moduleKey, $quantity, $bucket, $period->key);

        $this->checkThresholds($subscription, $moduleKey, $period);
    }

    /**
     * Replace a period's counters for one module with authoritative numbers.
     *
     * @param  iterable<int, UsageBucket>  $buckets
     */
    public function replace(Subscription $subscription, string $moduleKey, iterable $buckets, Period $period): void
    {
        $moduleId = $this->registry->recordId($moduleKey);

        DB::transaction(function () use ($subscription, $moduleId, $buckets, $period): void {
            UsageBilling::query('usage_counter')
                ->where('subscription_id', $subscription->getKey())
                ->where('module_id', $moduleId)
                ->where('period', $period->key)
                ->delete();

            foreach ($buckets as $bucket) {
                if ($bucket->quantity <= 0) {
                    continue;
                }

                UsageBilling::query('usage_counter')->create([
                    'subscription_id' => $subscription->getKey(),
                    'module_id' => $moduleId,
                    'period' => $period->key,
                    'attribution_key' => $bucket->key(),
                    'attribution_type' => $bucket->attributionType,
                    'attribution_id' => $bucket->attributionId,
                    'attribution_label' => $bucket->attributionLabel,
                    'quantity' => $bucket->quantity,
                    'synced_at' => now(),
                ]);
            }
        });

        $this->reader->forget($subscription, $moduleKey, $period);
    }

    /**
     * Fire a notification the first time a period crosses each configured
     * percentage of the allowance. The subscription event log doubles as the
     * "already told them" record, so a burst of usage cannot spam the tenant.
     */
    private function checkThresholds(Subscription $subscription, string $moduleKey, Period $period): void
    {
        $pricing = $subscription->pricingFor($moduleKey);

        if ($pricing === null || $pricing->isUnlimited() || (int) $pricing->included_quantity === 0) {
            return;
        }

        $used = $this->reader->totalFromDatabase($subscription, $moduleKey, $period);
        $percentage = $used / (int) $pricing->included_quantity * 100;

        foreach (UsageBilling::thresholds() as $threshold) {
            if ($percentage < $threshold) {
                continue;
            }

            $alreadyNotified = $subscription->events()
                ->where('type', SubscriptionEventType::UsageThreshold)
                ->where('meta->module', $moduleKey)
                ->where('meta->period', $period->key)
                ->where('meta->threshold', $threshold)
                ->exists();

            if ($alreadyNotified) {
                continue;
            }

            $subscription->events()->create([
                'type' => SubscriptionEventType::UsageThreshold,
                'to_value' => (string) $used,
                'meta' => [
                    'module' => $moduleKey,
                    'period' => $period->key,
                    'threshold' => $threshold,
                    'allowance' => $pricing->included_quantity,
                ],
            ]);

            UsageThresholdReached::dispatch($subscription, $moduleKey, $threshold, $used, (int) $pricing->included_quantity);
        }
    }
}

<?php

declare(strict_types=1);

namespace HoceineEl\UsageBilling\Concerns;

use HoceineEl\UsageBilling\Data\GateDecision;
use HoceineEl\UsageBilling\Data\ModuleUsageSummary;
use HoceineEl\UsageBilling\Data\UsageBucket;
use HoceineEl\UsageBilling\Models\Invoice;
use HoceineEl\UsageBilling\Models\Subscription;
use HoceineEl\UsageBilling\Services\ModuleGate;
use HoceineEl\UsageBilling\Services\UsageReader;
use HoceineEl\UsageBilling\Services\UsageRecorder;
use HoceineEl\UsageBilling\Services\UsageSynchronizer;
use HoceineEl\UsageBilling\Support\Period;
use HoceineEl\UsageBilling\UsageBilling;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;

/**
 * Satisfies the Subscribable contract for whatever model holds the
 * subscription.
 *
 * The resolved subscription is memoised per instance: a single request may ask
 * the gate a dozen times while rendering a panel, and each of those would
 * otherwise re-query.
 *
 * @mixin Model
 */
trait HasSubscription
{
    private ?Subscription $resolvedSubscription = null;

    private bool $subscriptionResolved = false;

    /**
     * Convenience relations only. Nothing inside this trait goes through them,
     * so a host model that already has a `subscriptions()` of its own — a
     * Cashier Billable, say — can alias these away without breaking metering.
     *
     * @return MorphMany<Subscription, $this>
     */
    public function subscriptions(): MorphMany
    {
        return $this->morphMany(UsageBilling::modelClass('subscription'), 'subscriber')
            ->latest('starts_at');
    }

    /** @return MorphMany<Invoice, $this> */
    public function invoices(): MorphMany
    {
        return $this->morphMany(UsageBilling::modelClass('invoice'), 'subscriber')
            ->latest('period');
    }

    public function currentSubscription(): ?Subscription
    {
        if ($this->subscriptionResolved) {
            return $this->resolvedSubscription;
        }

        $this->subscriptionResolved = true;

        return $this->resolvedSubscription = UsageBilling::query('subscription')
            ->with('plan.planModules.module')
            ->forSubscriber($this)
            ->current()
            ->latest('starts_at')
            ->first();
    }

    public function forgetSubscription(): void
    {
        $this->subscriptionResolved = false;
        $this->resolvedSubscription = null;
    }

    public function hasSubscription(): bool
    {
        return $this->currentSubscription() !== null;
    }

    public function moduleGate(string $moduleKey): GateDecision
    {
        return app(ModuleGate::class)->decide($this, $moduleKey);
    }

    public function canUse(string $moduleKey): bool
    {
        return $this->moduleGate($moduleKey)->allows();
    }

    public function ensureCanUse(string $moduleKey): void
    {
        app(ModuleGate::class)->ensure($this, $moduleKey);
    }

    public function canUseQuantity(string $moduleKey, int $quantity): bool
    {
        return app(ModuleGate::class)->allowsQuantity($this, $moduleKey, $quantity);
    }

    public function ensureCanUseQuantity(string $moduleKey, int $quantity): void
    {
        app(ModuleGate::class)->ensureQuantity($this, $moduleKey, $quantity);
    }

    public function meter(string $moduleKey, int $quantity = 1, ?Model $attribution = null): void
    {
        $subscription = $this->currentSubscription();

        if ($subscription === null) {
            return;
        }

        app(UsageRecorder::class)->record(
            $subscription,
            $moduleKey,
            $quantity,
            $attribution instanceof Model
                ? UsageBucket::for($attribution, $quantity)
                : UsageBucket::unattributed($quantity),
        );
    }

    public function usageFor(string $moduleKey, ?string $period = null): int
    {
        $subscription = $this->currentSubscription();

        if ($subscription === null) {
            return 0;
        }

        return app(UsageReader::class)->total(
            $subscription,
            $moduleKey,
            $period === null ? Period::current() : Period::fromKey($period),
        );
    }

    public function allowanceFor(string $moduleKey): ?int
    {
        return $this->currentSubscription()?->pricingFor($moduleKey)?->included_quantity;
    }

    public function remainingFor(string $moduleKey, ?string $period = null): ?int
    {
        $allowance = $this->allowanceFor($moduleKey);

        return $allowance === null
            ? null
            : max(0, $allowance - $this->usageFor($moduleKey, $period));
    }

    /**
     * Authoritative usage across every module in the plan, recomputed from the
     * source tables. Reads do not persist, so calling this to render a page
     * cannot rewrite the counters.
     *
     * @return Collection<string, ModuleUsageSummary>
     */
    public function usageSummary(?string $period = null, bool $persist = false): Collection
    {
        $subscription = $this->currentSubscription();

        if ($subscription === null) {
            return collect();
        }

        return app(UsageSynchronizer::class)->summarise(
            $subscription,
            $period === null ? Period::current() : Period::fromKey($period),
            $persist,
        );
    }

    public function usageLimitsEnforced(): bool
    {
        return (bool) config('usage-billing.enforcement.enabled', true);
    }
}

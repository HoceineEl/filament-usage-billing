<?php

declare(strict_types=1);

namespace HoceineEl\UsageBilling\Services;

use HoceineEl\UsageBilling\Contracts\Subscribable;
use HoceineEl\UsageBilling\Data\GateDecision;
use HoceineEl\UsageBilling\Data\UsageBucket;
use HoceineEl\UsageBilling\Enums\GateReason;
use HoceineEl\UsageBilling\Enums\ModuleType;
use HoceineEl\UsageBilling\Exceptions\ModuleUnavailableException;
use HoceineEl\UsageBilling\Models\Subscription;
use HoceineEl\UsageBilling\Support\Period;
use HoceineEl\UsageBilling\Support\UsageWindow;
use Illuminate\Database\Eloquent\Model;

/**
 * Answers whether a subscriber may use a module right now.
 *
 * Deliberately permissive by design: crossing an allowance accrues overage
 * rather than blocking. Only a plan-module's blocking limit stops work, which
 * exists so a runaway tenant cannot burn unbounded provider cost.
 */
class ModuleGate
{
    public function __construct(
        private readonly ModuleRegistry $registry,
        private readonly UsageReader $reader,
    ) {}

    public function decide(Model&Subscribable $subscriber, string $moduleKey, ?Period $period = null): GateDecision
    {
        if (! $subscriber->usageLimitsEnforced()) {
            return GateDecision::allowed();
        }

        $subscription = $subscriber->currentSubscription();

        if ($subscription === null) {
            return GateDecision::denied(GateReason::NoSubscription);
        }

        if (! $subscription->isUsable()) {
            return GateDecision::denied(GateReason::SubscriptionInactive);
        }

        $pricing = $subscription->pricingFor($moduleKey);

        if ($pricing === null) {
            return GateDecision::denied(GateReason::ModuleNotInPlan);
        }

        if ($pricing->isUnlimited()) {
            return GateDecision::allowed();
        }

        $used = $this->usedFor($subscription, $subscriber, $moduleKey, $period);

        return $pricing->permits($used)
            ? GateDecision::allowed($used, $pricing->included_quantity, $pricing->blockingLimit())
            : GateDecision::denied(
                GateReason::CeilingReached,
                $used,
                $pricing->included_quantity,
                $pricing->blockingLimit(),
            );
    }

    public function allows(Model&Subscribable $subscriber, string $moduleKey): bool
    {
        return $this->decide($subscriber, $moduleKey)->allows();
    }

    /**
     * A snapshot module is a live count — seats held right now — so caching it
     * would be answering with a number nobody maintains: nothing meters a seat,
     * and deactivating one has to free it immediately. Metered modules keep
     * reading the counter cache, which is what it is for, and a daily module
     * reads today's window in the subscriber's timezone.
     */
    private function usedFor(
        Subscription $subscription,
        Model&Subscribable $subscriber,
        string $moduleKey,
        ?Period $period,
    ): int {
        if ($this->registry->get($moduleKey)->type() !== ModuleType::Snapshot) {
            return $period === null
                ? $this->reader->windowTotal($subscription, $subscriber, $moduleKey, UsageWindow::current($this->registry->resetPeriod($moduleKey), $subscriber))
                : $this->reader->total($subscription, $moduleKey, $period);
        }

        $period ??= Period::current();

        return (int) app(UsageSynchronizer::class)
            ->bucketsFor($moduleKey, $subscriber, $period)
            ->sum(fn (UsageBucket $bucket): int => $bucket->quantity);
    }

    /**
     * @throws ModuleUnavailableException
     */
    public function ensure(Model&Subscribable $subscriber, string $moduleKey): void
    {
        $decision = $this->decide($subscriber, $moduleKey);

        if ($decision->denies()) {
            throw new ModuleUnavailableException($moduleKey, $decision);
        }
    }

    /**
     * Whether a batch of the given size fits before the blocking limit. Callers
     * that dispatch many jobs at once should use this rather than checking one
     * unit at a time, otherwise the whole batch is admitted on the strength of
     * a single free slot.
     */
    public function allowsQuantity(Model&Subscribable $subscriber, string $moduleKey, int $quantity): bool
    {
        return $this->decideQuantity($subscriber, $moduleKey, $quantity)->allows();
    }

    /**
     * @throws ModuleUnavailableException
     */
    public function ensureQuantity(Model&Subscribable $subscriber, string $moduleKey, int $quantity): void
    {
        $decision = $this->decideQuantity($subscriber, $moduleKey, $quantity);

        if ($decision->denies()) {
            throw new ModuleUnavailableException($moduleKey, $decision);
        }
    }

    public function decideQuantity(Model&Subscribable $subscriber, string $moduleKey, int $quantity): GateDecision
    {
        $decision = $this->decide($subscriber, $moduleKey);

        if ($decision->denies() || $decision->ceiling === null || $decision->used === null) {
            return $decision;
        }

        return $decision->used + $quantity <= $decision->ceiling
            ? $decision
            : GateDecision::denied(
                GateReason::CeilingReached,
                $decision->used,
                $decision->allowance,
                $decision->ceiling,
            );
    }
}

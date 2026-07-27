<?php

declare(strict_types=1);

namespace HoceineEl\UsageBilling\Contracts;

use HoceineEl\UsageBilling\Data\GateDecision;
use HoceineEl\UsageBilling\Models\Subscription;
use Illuminate\Database\Eloquent\Model;

/**
 * Implemented by whatever holds the subscription — a tenant, an organization,
 * a user. The HasSubscription trait satisfies all of it.
 */
interface Subscribable
{
    public function currentSubscription(): ?Subscription;

    public function canUse(string $moduleKey): bool;

    public function ensureCanUse(string $moduleKey): void;

    public function moduleGate(string $moduleKey): GateDecision;

    public function meter(string $moduleKey, int $quantity = 1, ?Model $attribution = null): void;

    public function usageFor(string $moduleKey, ?string $period = null): int;

    public function remainingFor(string $moduleKey, ?string $period = null): ?int;

    /**
     * Escape hatch for an application-wide switch that suspends enforcement,
     * such as an open beta. Returning false makes every gate permit usage
     * while still recording it.
     */
    public function usageLimitsEnforced(): bool;
}

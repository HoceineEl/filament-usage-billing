<?php

declare(strict_types=1);

namespace HoceineEl\UsageBilling\Models;

use Carbon\CarbonImmutable;
use HoceineEl\UsageBilling\Database\Factories\SubscriptionFactory;
use HoceineEl\UsageBilling\Enums\SubscriptionStatus;
use HoceineEl\UsageBilling\Models\Concerns\UsesConfiguredTable;
use HoceineEl\UsageBilling\UsageBilling;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Subscription extends Model
{
    use HasFactory;
    use UsesConfiguredTable;

    protected $guarded = [];

    public static function configKey(): string
    {
        return 'subscription';
    }

    protected static function newFactory(): SubscriptionFactory
    {
        return SubscriptionFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'seats' => 'integer',
            'renewal_seats' => 'integer',
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'trial_ends_at' => 'immutable_datetime',
            'grace_ends_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
        ];
    }

    /** @return MorphTo<Model, $this> */
    public function subscriber(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<Plan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(UsageBilling::modelClass('plan'));
    }

    /** @return HasMany<UsageCounter, $this> */
    public function usageCounters(): HasMany
    {
        return $this->hasMany(UsageBilling::modelClass('usage_counter'));
    }

    /** @return HasMany<Invoice, $this> */
    public function invoices(): HasMany
    {
        return $this->hasMany(UsageBilling::modelClass('invoice'));
    }

    /** @return HasMany<SubscriptionEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(UsageBilling::modelClass('subscription_event'));
    }

    /**
     * The subscription that governs the subscriber right now, whether or not
     * it currently grants access. A cabinet in arrears still has a plan; it
     * just cannot use it, and that is a different thing from having none.
     */
    #[Scope]
    protected function current(Builder $query): Builder
    {
        return $query
            ->whereNotIn('status', [SubscriptionStatus::Cancelled, SubscriptionStatus::Expired])
            ->where(function (Builder $query): void {
                $query->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            });
    }

    #[Scope]
    protected function usable(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query
                ->whereIn('status', [SubscriptionStatus::Trialing, SubscriptionStatus::Active])
                ->orWhere(function (Builder $query): void {
                    $query
                        ->where('status', SubscriptionStatus::PastDue)
                        ->where('grace_ends_at', '>', now());
                });
        });
    }

    #[Scope]
    protected function forSubscriber(Builder $query, Model $subscriber): Builder
    {
        return $query
            ->where('subscriber_type', $subscriber->getMorphClass())
            ->where('subscriber_id', $subscriber->getKey());
    }

    /**
     * Subscriptions that were live at any point during the given window, which
     * is what period close iterates: a cabinet that cancelled mid-month still
     * owes for the days it used.
     */
    #[Scope]
    protected function overlapping(Builder $query, CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return $query
            ->where('starts_at', '<=', $to)
            ->where(function (Builder $query) use ($from): void {
                $query->whereNull('ends_at')->orWhere('ends_at', '>=', $from);
            });
    }

    public function isUsable(): bool
    {
        // A term that has run out is not usable however healthy the status
        // looks — renewal has to be paid for before access continues.
        if ($this->hasTermExpired()) {
            return false;
        }

        if ($this->status->grantsAccess()) {
            return true;
        }

        return $this->status === SubscriptionStatus::PastDue
            && $this->grace_ends_at !== null
            && $this->grace_ends_at->isFuture();
    }

    /**
     * True once the paid term is over. The grace window is handled separately
     * by `isUsable()` via the PastDue branch.
     */
    public function hasTermExpired(): bool
    {
        return $this->ends_at !== null
            && $this->ends_at->isPast()
            && ($this->grace_ends_at === null || $this->grace_ends_at->isPast());
    }

    public function daysUntilTermEnds(): ?int
    {
        return $this->ends_at === null
            ? null
            : (int) ceil(now()->diffInDays($this->ends_at, false));
    }

    public function awaitsFirstPayment(): bool
    {
        return $this->status === SubscriptionStatus::PendingPayment;
    }

    public function onTrial(): bool
    {
        return $this->status === SubscriptionStatus::Trialing
            && $this->trial_ends_at !== null
            && $this->trial_ends_at->isFuture();
    }

    public function inGracePeriod(): bool
    {
        return $this->status === SubscriptionStatus::PastDue
            && $this->grace_ends_at !== null
            && $this->grace_ends_at->isFuture();
    }

    /**
     * Seats the allowances are computed from: what was paid for, or the plan
     * floor while nothing has been paid yet (a trial).
     */
    public function effectiveSeats(): int
    {
        return $this->plan?->billableSeats($this->seats) ?? max(1, (int) $this->seats);
    }

    /**
     * Seats the next term will be billed for.
     */
    public function seatsForNextTerm(): int
    {
        return $this->plan?->billableSeats($this->renewal_seats ?? $this->seats) ?? 1;
    }

    /**
     * What the current term is worth, for prorating a change of plan.
     */
    public function termPrice(): float
    {
        return $this->plan?->termPriceFor($this->seats) ?? 0.0;
    }

    public function pricingFor(Module|string $module): ?PlanModule
    {
        $pricing = $this->plan?->pricingFor($module);

        return $pricing === null ? null : $this->resolve($pricing);
    }

    /**
     * Every module row of the plan as it applies to this subscription.
     *
     * @return Collection<int, PlanModule>
     */
    public function resolvedPlanModules(): Collection
    {
        $this->loadMissing('plan.planModules.module');

        return $this->plan?->planModules
            ->map(fn (PlanModule $pricing): PlanModule => $this->resolve($pricing))
            ?? new Collection;
    }

    private function resolve(PlanModule $pricing): PlanModule
    {
        $plan = $this->plan;

        if (! $plan instanceof Plan || ! $plan->isSeatBased()) {
            return $pricing;
        }

        return $pricing->forSeats(
            $this->effectiveSeats(),
            $pricing->module?->key === $plan->seat_module,
        );
    }
}

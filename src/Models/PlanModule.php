<?php

declare(strict_types=1);

namespace HoceineEl\UsageBilling\Models;

use HoceineEl\UsageBilling\Enums\ModuleType;
use HoceineEl\UsageBilling\Enums\ResetPeriod;
use HoceineEl\UsageBilling\Models\Concerns\UsesConfiguredTable;
use HoceineEl\UsageBilling\UsageBilling;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What one plan charges for one module.
 *
 * The four meaningful shapes:
 *
 *   included_quantity null                          unlimited, never billed
 *   included_quantity N, unit_price set             soft overage, never blocks
 *   included_quantity N, unit_price set, ceiling M  overage N..M, blocked past M
 *   included_quantity N, unit_price null            blocked at N, no overage
 */
class PlanModule extends Model
{
    use HasFactory;
    use UsesConfiguredTable;

    protected $guarded = [];

    public static function configKey(): string
    {
        return 'plan_module';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'included_quantity' => 'integer',
            'unit_price_ht' => 'float',
            'hard_ceiling' => 'integer',
            'settings' => 'array',
        ];
    }

    /** @return BelongsTo<Plan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(UsageBilling::modelClass('plan'));
    }

    /** @return BelongsTo<Module, $this> */
    public function module(): BelongsTo
    {
        return $this->belongsTo(UsageBilling::modelClass('module'));
    }

    public function isUnlimited(): bool
    {
        return $this->included_quantity === null;
    }

    public function billsOverage(): bool
    {
        return ! $this->isUnlimited() && $this->unit_price_ht !== null;
    }

    /**
     * The point at which usage stops being permitted. Null means it never
     * stops. An allowance with no unit price is itself the wall.
     */
    public function blockingLimit(): ?int
    {
        if ($this->isUnlimited()) {
            return null;
        }

        if (! $this->billsOverage()) {
            return $this->included_quantity;
        }

        return $this->hard_ceiling;
    }

    public function permits(int $used): bool
    {
        $limit = $this->blockingLimit();

        return $limit === null || $used < $limit;
    }

    public function overageQuantity(int $used): int
    {
        if (! $this->billsOverage()) {
            return 0;
        }

        return max(0, $used - (int) $this->included_quantity);
    }

    public function overageAmountHt(int $used): float
    {
        return round($this->overageQuantity($used) * (float) $this->unit_price_ht, 2);
    }

    /**
     * One line describing what this plan grants for the module.
     *
     * Metered allowances reset every period, so they read "per month" and
     * carry the twelve-month equivalent for anyone pricing a term. A snapshot
     * allowance is a ceiling held at any instant — a seat count does not
     * refill — so neither figure applies to it. A daily allowance reads "per
     * day" instead.
     */
    public function summaryLabel(?ModuleType $type = null, ?ResetPeriod $resetPeriod = null): string
    {
        if ($this->isUnlimited()) {
            return __('usage-billing::billing.plan.summary.unlimited');
        }

        $type ??= $this->module?->type();
        $resetPeriod ??= $this->module?->resetPeriod() ?? ResetPeriod::Month;
        $monthly = match (true) {
            $type !== ModuleType::Metered => '',
            $resetPeriod === ResetPeriod::Day => '_daily',
            default => '_monthly',
        };

        $replacements = [
            'included' => static::formatQuantity((int) $this->included_quantity),
            'yearly' => static::formatQuantity((int) $this->included_quantity * 12),
        ];

        if (! $this->billsOverage()) {
            return __("usage-billing::billing.plan.summary.capped{$monthly}", $replacements);
        }

        return __("usage-billing::billing.plan.summary.metered{$monthly}", [
            ...$replacements,
            'price' => rtrim(rtrim(number_format((float) $this->unit_price_ht, 2, ',', ' '), '0'), ','),
            'currency' => UsageBilling::currency(),
        ]);
    }

    private static function formatQuantity(int $quantity): string
    {
        return number_format($quantity, 0, ',', ' ');
    }
}

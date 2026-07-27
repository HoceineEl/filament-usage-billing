<?php

declare(strict_types=1);

namespace HoceineEl\UsageBilling\Models;

use HoceineEl\UsageBilling\Models\Concerns\UsesConfiguredTable;
use HoceineEl\UsageBilling\UsageBilling;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cached usage for one subscription, module, period and attribution.
 *
 * Only ever a cache. `usage-billing:close-period` recomputes every row from
 * the module queries before an invoice is built, so drift here costs an
 * inaccurate progress bar, never an inaccurate facture.
 */
class UsageCounter extends Model
{
    use HasFactory;
    use UsesConfiguredTable;

    protected $guarded = [];

    public static function configKey(): string
    {
        return 'usage_counter';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'attribution_id' => 'integer',
            'synced_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Subscription, $this> */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(UsageBilling::modelClass('subscription'));
    }

    /** @return BelongsTo<Module, $this> */
    public function module(): BelongsTo
    {
        return $this->belongsTo(UsageBilling::modelClass('module'));
    }

    #[Scope]
    protected function forPeriod(Builder $query, string $period): Builder
    {
        return $query->where('period', $period);
    }

    #[Scope]
    protected function attributed(Builder $query): Builder
    {
        return $query->where('attribution_key', '!=', '');
    }

    public function isAttributed(): bool
    {
        return $this->attribution_key !== '';
    }
}

<?php

declare(strict_types=1);

namespace HoceineEl\UsageBilling\Models;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use HoceineEl\UsageBilling\Database\Factories\PlanFactory;
use HoceineEl\UsageBilling\Models\Concerns\UsesConfiguredTable;
use HoceineEl\UsageBilling\UsageBilling;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\App;

class Plan extends Model
{
    use HasFactory;
    use SoftDeletes;
    use UsesConfiguredTable;

    protected $guarded = [];

    public static function configKey(): string
    {
        return 'plan';
    }

    protected static function newFactory(): PlanFactory
    {
        return PlanFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'name' => 'array',
            'description' => 'array',
            'price_ht' => 'decimal:2',
            'seat_price_ht' => 'decimal:2',
            'min_seats' => 'integer',
            'tva_rate' => 'decimal:2',
            'term_months' => 'integer',
            'renewal_notice_days' => 'integer',
            'trial_days' => 'integer',
            'grace_days' => 'integer',
            'payment_term_days' => 'integer',
            'is_active' => 'boolean',
            'is_public' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /** @return HasMany<PlanModule, $this> */
    public function planModules(): HasMany
    {
        return $this->hasMany(UsageBilling::modelClass('plan_module'));
    }

    /** @return BelongsToMany<Module, $this> */
    public function modules(): BelongsToMany
    {
        return $this->belongsToMany(
            UsageBilling::modelClass('module'),
            UsageBilling::table('plan_module'),
        )->withPivot(['included_quantity', 'unit_price_ht', 'hard_ceiling', 'settings'])
            ->withTimestamps();
    }

    /** @return HasMany<Subscription, $this> */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(UsageBilling::modelClass('subscription'));
    }

    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    #[Scope]
    protected function public(Builder $query): Builder
    {
        return $query->where('is_public', true);
    }

    #[Scope]
    protected function ordered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('price_ht');
    }

    /**
     * End of a term that starts on the given date. A 12-month term running
     * from 1 Jan ends on 31 Dec, not on 1 Jan of the next year.
     */
    public function termEndFrom(CarbonInterface $start): CarbonImmutable
    {
        return CarbonImmutable::instance($start)
            ->addMonthsNoOverflow(max(1, (int) $this->term_months))
            ->subDay()
            ->endOfDay();
    }

    /**
     * A seat plan sells units of one module (clients, users) at a price each,
     * instead of one flat price for the term.
     */
    public function isSeatBased(): bool
    {
        return filled($this->seat_module) && $this->seat_price_ht !== null;
    }

    /**
     * Seats a term is billed for: what was asked, never below the plan floor.
     */
    public function billableSeats(?int $requested): int
    {
        return max((int) ($requested ?? 0), (int) ($this->min_seats ?? 0), 1);
    }

    /**
     * What one term costs: the flat price, or seats at the seat price.
     */
    public function termPriceFor(?int $seats = null): float
    {
        if (! $this->isSeatBased()) {
            return (float) $this->price_ht;
        }

        return round((float) $this->price_ht + $this->billableSeats($seats) * (float) $this->seat_price_ht, 2);
    }

    public function isAnnual(): bool
    {
        return (int) $this->term_months === 12;
    }

    public function displayName(?string $locale = null): string
    {
        return $this->translated('name', $locale) ?? $this->slug;
    }

    public function displayDescription(?string $locale = null): ?string
    {
        return $this->translated('description', $locale);
    }

    /**
     * Pricing row for one module, or null when the plan does not include the
     * module at all — which is how a feature is switched off entirely.
     */
    public function pricingFor(Module|string $module): ?PlanModule
    {
        $rows = $this->relationLoaded('planModules')
            ? $this->planModules
            : $this->planModules()->with('module')->get();

        return $module instanceof Module
            ? $rows->firstWhere('module_id', $module->getKey())
            : $rows->first(fn (PlanModule $row): bool => $row->module?->key === $module);
    }

    private function translated(string $attribute, ?string $locale): ?string
    {
        $values = $this->getAttribute($attribute);

        if (! is_array($values) || $values === []) {
            return null;
        }

        $locale ??= App::getLocale();

        return $values[$locale]
            ?? $values[config('app.fallback_locale')]
            ?? (is_string(reset($values)) ? reset($values) : null);
    }
}

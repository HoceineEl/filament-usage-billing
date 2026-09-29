<?php

declare(strict_types=1);

namespace HoceineEl\UsageBilling\Models;

use HoceineEl\UsageBilling\Contracts\MeteredModule;
use HoceineEl\UsageBilling\Database\Factories\ModuleFactory;
use HoceineEl\UsageBilling\Enums\ModuleType;
use HoceineEl\UsageBilling\Enums\ResetPeriod;
use HoceineEl\UsageBilling\Exceptions\UnknownModuleException;
use HoceineEl\UsageBilling\Models\Concerns\UsesConfiguredTable;
use HoceineEl\UsageBilling\Services\ModuleRegistry;
use HoceineEl\UsageBilling\UsageBilling;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Database mirror of a MeteredModule class, so plan pricing rows have
 * something stable to point at. The class itself stays the source of truth for
 * behaviour; this row only carries identity and activation.
 */
class Module extends Model
{
    use HasFactory;
    use UsesConfiguredTable;

    protected $guarded = [];

    public static function configKey(): string
    {
        return 'module';
    }

    protected static function newFactory(): ModuleFactory
    {
        return ModuleFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /** @return HasMany<PlanModule, $this> */
    public function planModules(): HasMany
    {
        return $this->hasMany(UsageBilling::modelClass('plan_module'));
    }

    /** @return BelongsToMany<Plan, $this> */
    public function plans(): BelongsToMany
    {
        return $this->belongsToMany(
            UsageBilling::modelClass('plan'),
            UsageBilling::table('plan_module'),
        )->withPivot(['included_quantity', 'unit_price_ht', 'hard_ceiling', 'settings'])
            ->withTimestamps();
    }

    /** @return HasMany<UsageCounter, $this> */
    public function usageCounters(): HasMany
    {
        return $this->hasMany(UsageBilling::modelClass('usage_counter'));
    }

    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    #[Scope]
    protected function ordered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('key');
    }

    public function instance(): MeteredModule
    {
        $class = $this->class;

        if (! is_string($class) || ! class_exists($class) || ! is_subclass_of($class, MeteredModule::class)) {
            throw UnknownModuleException::forClass((string) $class);
        }

        return app($class);
    }

    public function label(): string
    {
        return $this->instance()->label();
    }

    public function unitLabel(): string
    {
        return $this->instance()->unitLabel();
    }

    public function type(): ModuleType
    {
        return $this->instance()->type();
    }

    public function resetPeriod(): ResetPeriod
    {
        $registry = app(ModuleRegistry::class);

        return $registry->has((string) $this->key) ? $registry->resetPeriod((string) $this->key) : ResetPeriod::Month;
    }
}

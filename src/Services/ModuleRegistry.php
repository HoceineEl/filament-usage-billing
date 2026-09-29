<?php

declare(strict_types=1);

namespace HoceineEl\UsageBilling\Services;

use HoceineEl\UsageBilling\Contracts\HasResetPeriod;
use HoceineEl\UsageBilling\Contracts\MeteredModule;
use HoceineEl\UsageBilling\Enums\ModuleType;
use HoceineEl\UsageBilling\Enums\ResetPeriod;
use HoceineEl\UsageBilling\Exceptions\UnknownModuleException;
use HoceineEl\UsageBilling\Models\Module;
use HoceineEl\UsageBilling\UsageBilling;
use Illuminate\Support\Collection;

/**
 * Bridges the configured module classes and their database rows.
 *
 * Resolution is memoised for the request: gates and meters hit this on hot
 * paths, and neither the config list nor the registry table changes mid-request.
 */
class ModuleRegistry
{
    /** @var Collection<string, MeteredModule>|null */
    private ?Collection $instances = null;

    /** @var Collection<string, Module>|null */
    private ?Collection $records = null;

    /**
     * @return Collection<string, MeteredModule>
     */
    public function all(): Collection
    {
        return $this->instances ??= collect((array) config('usage-billing.modules', []))
            ->mapWithKeys(function (string $class): array {
                if (! is_subclass_of($class, MeteredModule::class)) {
                    throw UnknownModuleException::forClass($class);
                }

                return [$class::key() => app($class)];
            });
    }

    public function get(string $key): MeteredModule
    {
        return $this->all()->get($key) ?? throw UnknownModuleException::forKey($key);
    }

    /**
     * How often the module's allowance refills. Snapshot modules measure a
     * state rather than a stream, so they never reset.
     */
    public function resetPeriod(string $key): ResetPeriod
    {
        $module = $this->get($key);

        return $module instanceof HasResetPeriod && $module->type() === ModuleType::Metered
            ? $module->resetPeriod()
            : ResetPeriod::Month;
    }

    public function has(string $key): bool
    {
        return $this->all()->has($key);
    }

    /**
     * @return Collection<string, Module>
     */
    public function records(): Collection
    {
        return $this->records ??= UsageBilling::query('module')->get()->keyBy('key');
    }

    public function record(string $key): Module
    {
        $record = $this->records()->get($key);

        if (! $record instanceof Module) {
            throw UnknownModuleException::forKey($key);
        }

        return $record;
    }

    public function recordId(string $key): int
    {
        return (int) $this->record($key)->getKey();
    }

    /**
     * Reconcile the database registry with the configured classes. Modules that
     * disappear from config are deactivated rather than deleted, because plan
     * pricing rows and historical invoice lines still point at them.
     *
     * @return array{created: int, updated: int, deactivated: int}
     */
    public function sync(): array
    {
        $this->records = null;

        $created = 0;
        $updated = 0;
        $order = 0;

        foreach ($this->all() as $key => $module) {
            /** @var Module $record */
            $record = UsageBilling::query('module')->firstOrNew(['key' => $key]);

            $record->fill([
                'class' => $module::class,
                'is_active' => true,
                'sort_order' => $order++,
            ]);

            if (! $record->exists) {
                $created++;
            } elseif ($record->isDirty()) {
                $updated++;
            }

            $record->save();
        }

        $deactivated = UsageBilling::query('module')
            ->whereNotIn('key', $this->all()->keys()->all())
            ->where('is_active', true)
            ->update(['is_active' => false]);

        $this->records = null;

        return [
            'created' => $created,
            'updated' => $updated,
            'deactivated' => (int) $deactivated,
        ];
    }

    public function flush(): void
    {
        $this->instances = null;
        $this->records = null;
    }
}

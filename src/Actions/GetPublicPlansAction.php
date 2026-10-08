<?php

declare(strict_types=1);

namespace HoceineEl\UsageBilling\Actions;

use HoceineEl\UsageBilling\Models\Plan;
use HoceineEl\UsageBilling\Models\PlanModule;
use HoceineEl\UsageBilling\UsageBilling;
use Illuminate\Support\Collection;

/**
 * The public catalogue, read from the plans table rather than restated in a
 * marketing file, so repricing a plan is the only edit a price change needs.
 *
 * Module labels resolve through the registered module classes, which keeps the
 * feature list honest: a module missing from the plan is one the gate refuses.
 */
class GetPublicPlansAction
{
    /**
     * @return Collection<int, array{
     *     slug: string,
     *     name: string,
     *     description: ?string,
     *     price_ht: float,
     *     price_ttc: float,
     *     monthly_equivalent: float,
     *     currency: string,
     *     tva_rate: float,
     *     term_months: int,
     *     trial_days: int,
     *     seat_module: ?string,
     *     seat_price_ht: ?float,
     *     min_seats: ?int,
     *     is_recommended: bool,
     *     modules: array<string, array{key: string, label: string, unit: string, allowance: ?int, reset_period: string, unit_price_ht: ?float, ceiling: ?int, summary: string}>,
     *     features: array<int, array{label: string, allowance: ?int, unit: string}>
     * }>
     */
    public function execute(): Collection
    {
        $recommended = config('usage-billing.recommended_plan');

        return UsageBilling::query('plan')
            ->active()
            ->public()
            ->ordered()
            ->with('planModules.module')
            ->get()
            ->map(fn (Plan $plan): array => $this->present($plan, is_string($recommended) ? $recommended : null));
    }

    /**
     * @return array<string, mixed>
     */
    protected function present(Plan $plan, ?string $recommended): array
    {
        $months = max(1, (int) $plan->term_months);
        $modules = $this->modules($plan);

        return [
            'slug' => $plan->slug,
            'name' => $plan->displayName(),
            'description' => $plan->displayDescription(),
            'price_ht' => (float) $plan->price_ht,
            'price_ttc' => round((float) $plan->price_ht * (1 + (float) $plan->tva_rate / 100), 2),
            'monthly_equivalent' => round((float) $plan->price_ht / $months, 2),
            'currency' => $plan->currency ?? UsageBilling::currency(),
            'tva_rate' => (float) $plan->tva_rate,
            'term_months' => $months,
            'trial_days' => (int) $plan->trial_days,
            'seat_module' => $plan->isSeatBased() ? $plan->seat_module : null,
            'seat_price_ht' => $plan->isSeatBased() ? (float) $plan->seat_price_ht : null,
            'min_seats' => $plan->isSeatBased() ? $plan->billableSeats(null) : null,
            'is_recommended' => $plan->slug === $recommended,
            'modules' => $modules,
            'features' => array_values(array_map(fn (array $module): array => [
                'label' => $module['label'],
                'allowance' => $module['allowance'],
                'unit' => $module['unit'],
            ], $modules)),
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function modules(Plan $plan): array
    {
        return $plan->planModules
            ->filter(fn (PlanModule $row): bool => $row->module?->is_active === true)
            ->sortBy(fn (PlanModule $row): int => (int) $row->module->sort_order)
            ->mapWithKeys(fn (PlanModule $row): array => [(string) $row->module->key => [
                'key' => (string) $row->module->key,
                'label' => $row->module->label(),
                'unit' => $row->module->unitLabel(),
                'allowance' => $row->included_quantity,
                'reset_period' => $row->module->resetPeriod()->value,
                'unit_price_ht' => $row->unit_price_ht,
                'ceiling' => $row->hard_ceiling,
                'summary' => $row->summaryLabel(),
            ]])
            ->all();
    }
}

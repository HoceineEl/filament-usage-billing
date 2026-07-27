<?php

declare(strict_types=1);

namespace HoceineEl\UsageBilling\Database\Factories;

use HoceineEl\UsageBilling\Models\Plan;
use HoceineEl\UsageBilling\UsageBilling;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    protected $model = Plan::class;

    public function modelName(): string
    {
        return UsageBilling::modelClass('plan');
    }

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->unique()->words(2, true);

        return [
            'slug' => str($name)->slug()->value(),
            'name' => ['en' => ucfirst($name), 'fr' => ucfirst($name)],
            'description' => null,
            'price_ht' => 500,
            'currency' => 'MAD',
            'tva_rate' => 20,
            'trial_days' => 0,
            'grace_days' => 7,
            'payment_term_days' => 30,
            'is_active' => true,
            'is_public' => true,
            'sort_order' => 0,
        ];
    }

    public function free(): static
    {
        return $this->state(['price_ht' => 0]);
    }

    public function withTrial(int $days = 14): static
    {
        return $this->state(['trial_days' => $days]);
    }
}

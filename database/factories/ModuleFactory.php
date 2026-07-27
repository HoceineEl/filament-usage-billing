<?php

declare(strict_types=1);

namespace HoceineEl\UsageBilling\Database\Factories;

use HoceineEl\UsageBilling\Models\Module;
use HoceineEl\UsageBilling\UsageBilling;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Module>
 */
class ModuleFactory extends Factory
{
    protected $model = Module::class;

    public function modelName(): string
    {
        return UsageBilling::modelClass('module');
    }

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => $this->faker->unique()->slug(1),
            'class' => '',
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}

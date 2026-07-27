<?php

declare(strict_types=1);

namespace HoceineEl\UsageBilling\Database\Factories;

use HoceineEl\UsageBilling\Enums\SubscriptionStatus;
use HoceineEl\UsageBilling\Models\Subscription;
use HoceineEl\UsageBilling\UsageBilling;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    public function modelName(): string
    {
        return UsageBilling::modelClass('subscription');
    }

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'plan_id' => UsageBilling::modelClass('plan')::factory(),
            'status' => SubscriptionStatus::Active,
            'starts_at' => now()->subMonth(),
        ];
    }

    public function pastDue(bool $withinGrace = true): static
    {
        return $this->state([
            'status' => SubscriptionStatus::PastDue,
            'grace_ends_at' => $withinGrace ? now()->addWeek() : now()->subDay(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state([
            'status' => SubscriptionStatus::Cancelled,
            'cancelled_at' => now(),
            'ends_at' => now(),
        ]);
    }
}

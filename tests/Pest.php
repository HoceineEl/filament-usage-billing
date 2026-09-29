<?php

declare(strict_types=1);

use HoceineEl\UsageBilling\Enums\PaymentMethod;
use HoceineEl\UsageBilling\Models\Invoice;
use HoceineEl\UsageBilling\Models\Module;
use HoceineEl\UsageBilling\Models\Plan;
use HoceineEl\UsageBilling\Models\PlanModule;
use HoceineEl\UsageBilling\Models\Subscription;
use HoceineEl\UsageBilling\Services\SubscriptionManager;
use HoceineEl\UsageBilling\Tests\Fixtures\Cabinet;
use HoceineEl\UsageBilling\Tests\Fixtures\Customer;
use HoceineEl\UsageBilling\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(TestCase::class, RefreshDatabase::class)->in(__DIR__);

function cabinet(array $attributes = []): Cabinet
{
    return Cabinet::create([
        'name' => 'Cabinet Test',
        'email' => 'cabinet@example.test',
        'ice' => '001234567000089',
        ...$attributes,
    ]);
}

function customer(Cabinet $cabinet, string $name = 'Client A', bool $active = true): Customer
{
    return Customer::create([
        'cabinet_id' => $cabinet->getKey(),
        'name' => $name,
        'active' => $active,
    ]);
}

function document(Cabinet $cabinet, ?Customer $customer = null, bool $failed = false, ?string $at = null): void
{
    DB::table('documents')->insert([
        'cabinet_id' => $cabinet->getKey(),
        'customer_id' => $customer?->getKey(),
        'failed' => $failed,
        'created_at' => $at ?? now(),
        'updated_at' => $at ?? now(),
    ]);
}

/**
 * Build a plan whose module pricing is described inline, e.g.
 * `['documents' => ['included' => 100, 'price' => 0.5, 'ceiling' => 500]]`.
 *
 * @param  array<string, array{included?: ?int, price?: ?float, ceiling?: ?int}>  $modules
 */
function planWith(array $modules, float $basePrice = 500): Plan
{
    /** @var Plan $plan */
    $plan = Plan::factory()->create(['price_ht' => $basePrice]);

    foreach ($modules as $key => $pricing) {
        PlanModule::create([
            'plan_id' => $plan->getKey(),
            'module_id' => Module::where('key', $key)->value('id'),
            'included_quantity' => $pricing['included'] ?? null,
            'unit_price_ht' => $pricing['price'] ?? null,
            'hard_ceiling' => $pricing['ceiling'] ?? null,
        ]);
    }

    return $plan->load('planModules.module');
}

function subscribe(Cabinet $cabinet, Plan $plan, array $attributes = []): Subscription
{
    $subscription = Subscription::factory()->create([
        'subscriber_type' => $cabinet->getMorphClass(),
        'subscriber_id' => $cabinet->getKey(),
        'plan_id' => $plan->getKey(),
        ...$attributes,
    ]);

    $cabinet->forgetSubscription();

    return $subscription;
}

function annualPlan(array $modules = ['documents' => ['included' => 100, 'price' => 1]], float $price = 2490): Plan
{
    return tap(planWith($modules, basePrice: $price), fn (Plan $plan) => $plan->forceFill([
        'term_months' => 12,
        'renewal_notice_days' => 30,
        'grace_days' => 10,
        'payment_term_days' => 30,
    ])->save());
}

function settle(Invoice $invoice): void
{
    $manager = app(SubscriptionManager::class);

    $manager->validatePayment($manager->declarePayment($invoice, [
        'method' => PaymentMethod::Virement,
        'amount' => (float) $invoice->total_ttc,
    ]));
}

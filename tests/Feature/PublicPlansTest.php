<?php

declare(strict_types=1);

use HoceineEl\UsageBilling\Actions\GetPublicPlansAction;
use HoceineEl\UsageBilling\Models\Plan;

it('lists active public plans with what each includes', function (): void {
    config()->set('usage-billing.recommended_plan', 'pro');

    $pro = annualPlan(['documents' => ['included' => 100, 'price' => 1], 'messages' => ['included' => 50]], price: 1200);
    $pro->forceFill(['slug' => 'pro', 'sort_order' => 2])->save();
    $starter = annualPlan(['documents' => ['included' => 10]], price: 600);
    $starter->forceFill(['slug' => 'starter', 'sort_order' => 1])->save();

    Plan::factory()->create(['slug' => 'hidden', 'is_public' => false]);
    Plan::factory()->create(['slug' => 'retired', 'is_active' => false]);

    $plans = app(GetPublicPlansAction::class)->execute();
    $proRow = $plans->firstWhere('slug', 'pro');

    expect($plans->pluck('slug')->all())->toBe(['starter', 'pro'])
        ->and($proRow['is_recommended'])->toBeTrue()
        ->and($proRow['monthly_equivalent'])->toBe(100.0)
        ->and($proRow['price_ttc'])->toBe(1440.0)
        ->and($proRow['modules']['messages']['reset_period'])->toBe('day')
        ->and($proRow['modules']['documents']['allowance'])->toBe(100)
        ->and($proRow['features'])->toHaveCount(2);
});

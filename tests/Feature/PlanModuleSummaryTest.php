<?php

declare(strict_types=1);

use HoceineEl\UsageBilling\Enums\ModuleType;
use HoceineEl\UsageBilling\Models\PlanModule;

it('reads a metered allowance as a monthly budget, with the term equivalent beside it', function (): void {
    $pricing = planWith(['documents' => ['included' => 500]])->planModules->first();

    expect($pricing->summaryLabel())->toBe('500 max / month · 6 000 / year');
});

it('reads a snapshot allowance as a standing ceiling, since seats do not refill', function (): void {
    $pricing = planWith(['seats' => ['included' => 2]])->planModules->first();

    expect($pricing->summaryLabel())->toBe('2 max');
});

it('marks metered overage pricing as monthly too', function (): void {
    $pricing = planWith(['documents' => ['included' => 100, 'price' => 0.5]])->planModules->first();

    expect($pricing->summaryLabel())->toBe('100 / month · 1 200 / year, then 0,5 MAD');
});

it('never dates an unlimited allowance', function (): void {
    $pricing = planWith(['documents' => ['included' => null]])->planModules->first();

    expect($pricing->summaryLabel())->toBe('unlimited');
});

it('takes the type from the caller when the row carries no module relation', function (): void {
    $pricing = new PlanModule(['included_quantity' => 30]);

    expect($pricing->summaryLabel(ModuleType::Metered))->toBe('30 max / month · 360 / year')
        ->and($pricing->summaryLabel(ModuleType::Snapshot))->toBe('30 max')
        ->and($pricing->summaryLabel())->toBe('30 max');
});

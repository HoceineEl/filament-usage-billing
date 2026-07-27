<?php

declare(strict_types=1);

use HoceineEl\UsageBilling\Enums\GateReason;
use HoceineEl\UsageBilling\Enums\SubscriptionStatus;
use HoceineEl\UsageBilling\Exceptions\ModuleUnavailableException;
use HoceineEl\UsageBilling\Tests\Fixtures\Cabinet;

afterEach(fn () => Cabinet::$limitsEnforced = true);

it('denies a cabinet with no subscription', function (): void {
    expect(cabinet()->moduleGate('documents')->reason)->toBe(GateReason::NoSubscription);
});

it('denies a module the plan does not carry', function (): void {
    $cabinet = cabinet();
    subscribe($cabinet, planWith(['seats' => ['included' => 10]]));

    expect($cabinet->moduleGate('documents')->reason)->toBe(GateReason::ModuleNotInPlan);
});

it('denies once the subscription has fallen out of its grace window', function (): void {
    $cabinet = cabinet();
    subscribe($cabinet, planWith(['documents' => ['included' => 10, 'price' => 1]]), [
        'status' => SubscriptionStatus::PastDue,
        'grace_ends_at' => now()->subDay(),
    ]);

    expect($cabinet->moduleGate('documents')->reason)->toBe(GateReason::SubscriptionInactive);
});

it('still allows work inside the grace window', function (): void {
    $cabinet = cabinet();
    subscribe($cabinet, planWith(['documents' => ['included' => 10, 'price' => 1]]), [
        'status' => SubscriptionStatus::PastDue,
        'grace_ends_at' => now()->addWeek(),
    ]);

    expect($cabinet->canUse('documents'))->toBeTrue();
});

it('lets usage past the allowance when overage is priced', function (): void {
    $cabinet = cabinet();
    subscribe($cabinet, planWith(['documents' => ['included' => 2, 'price' => 1.5]]));

    $cabinet->meter('documents', 5);

    $decision = $cabinet->moduleGate('documents');

    expect($decision->allows())->toBeTrue()
        ->and($decision->isOverAllowance())->toBeTrue()
        ->and($decision->remaining())->toBe(0);
});

it('blocks at the allowance when no overage price is set', function (): void {
    $cabinet = cabinet();
    subscribe($cabinet, planWith(['documents' => ['included' => 3]]));

    $cabinet->meter('documents', 3);

    expect($cabinet->moduleGate('documents')->reason)->toBe(GateReason::CeilingReached);
});

it('blocks at the hard ceiling but not before it', function (): void {
    $cabinet = cabinet();
    subscribe($cabinet, planWith(['documents' => ['included' => 2, 'price' => 1, 'ceiling' => 5]]));

    $cabinet->meter('documents', 4);
    expect($cabinet->canUse('documents'))->toBeTrue();

    $cabinet->meter('documents', 1);
    expect($cabinet->moduleGate('documents')->reason)->toBe(GateReason::CeilingReached);
});

it('never blocks an unlimited module', function (): void {
    $cabinet = cabinet();
    subscribe($cabinet, planWith(['documents' => ['included' => null]]));

    $cabinet->meter('documents', 10_000);

    expect($cabinet->canUse('documents'))->toBeTrue();
});

it('refuses a batch that would overshoot the ceiling', function (): void {
    $cabinet = cabinet();
    subscribe($cabinet, planWith(['documents' => ['included' => 2, 'price' => 1, 'ceiling' => 10]]));

    $cabinet->meter('documents', 8);

    expect($cabinet->canUseQuantity('documents', 5))->toBeFalse()
        ->and($cabinet->canUseQuantity('documents', 2))->toBeTrue();
});

it('throws for an overshooting batch even while a single unit would pass', function (): void {
    $cabinet = cabinet();
    subscribe($cabinet, planWith(['documents' => ['included' => 1, 'price' => 1, 'ceiling' => 5]]));

    $cabinet->meter('documents', 4);

    // One slot is left, so the single-unit gate says yes. The batch must not
    // inherit that answer.
    expect($cabinet->canUse('documents'))->toBeTrue()
        ->and(fn () => $cabinet->ensureCanUseQuantity('documents', 3))
        ->toThrow(ModuleUnavailableException::class);
});

it('lets a batch through when it fits exactly', function (): void {
    $cabinet = cabinet();
    subscribe($cabinet, planWith(['documents' => ['included' => 1, 'price' => 1, 'ceiling' => 5]]));

    $cabinet->meter('documents', 3);
    $cabinet->ensureCanUseQuantity('documents', 2);

    expect($cabinet->canUseQuantity('documents', 2))->toBeTrue();
});

it('never limits a batch on an unlimited module', function (): void {
    $cabinet = cabinet();
    subscribe($cabinet, planWith(['documents' => ['included' => null]]));

    expect($cabinet->canUseQuantity('documents', 100_000))->toBeTrue();
});

it('throws a reason-carrying exception from ensureCanUse', function (): void {
    $cabinet = cabinet();
    subscribe($cabinet, planWith(['documents' => ['included' => 1]]));
    $cabinet->meter('documents', 1);

    expect(fn () => $cabinet->ensureCanUse('documents'))
        ->toThrow(
            ModuleUnavailableException::class,
        );
});

it('permits everything while limits are switched off', function (): void {
    Cabinet::$limitsEnforced = false;

    expect(cabinet()->canUse('documents'))->toBeTrue();
});

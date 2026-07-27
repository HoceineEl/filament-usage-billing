<?php

declare(strict_types=1);

use HoceineEl\UsageBilling\Enums\SubscriptionEventType;
use HoceineEl\UsageBilling\Events\UsageThresholdReached;
use HoceineEl\UsageBilling\Models\UsageCounter;
use HoceineEl\UsageBilling\Services\UsageSynchronizer;
use HoceineEl\UsageBilling\Support\Period;
use Illuminate\Support\Facades\Event;

it('counts a first increment exactly once', function (): void {
    $cabinet = cabinet();
    subscribe($cabinet, planWith(['documents' => ['included' => 100, 'price' => 1]]));

    $cabinet->meter('documents', 3);

    expect($cabinet->usageFor('documents'))->toBe(3);
});

it('accumulates repeated increments on one counter row', function (): void {
    $cabinet = cabinet();
    subscribe($cabinet, planWith(['documents' => ['included' => 100, 'price' => 1]]));

    $cabinet->meter('documents', 2);
    $cabinet->meter('documents', 5);

    expect($cabinet->usageFor('documents'))->toBe(7)
        ->and(UsageCounter::count())->toBe(1);
});

it('keeps a separate counter row per attributed client', function (): void {
    $cabinet = cabinet();
    subscribe($cabinet, planWith(['documents' => ['included' => 100, 'price' => 1]]));
    $alpha = customer($cabinet, 'Alpha');
    $beta = customer($cabinet, 'Beta');

    $cabinet->meter('documents', 2, $alpha);
    $cabinet->meter('documents', 3, $beta);
    $cabinet->meter('documents', 1);

    expect($cabinet->usageFor('documents'))->toBe(6)
        ->and(UsageCounter::count())->toBe(3)
        ->and(UsageCounter::where('attribution_key', '')->value('quantity'))->toBe(1);
});

it('reports remaining allowance and stops at zero', function (): void {
    $cabinet = cabinet();
    subscribe($cabinet, planWith(['documents' => ['included' => 5, 'price' => 1]]));

    $cabinet->meter('documents', 2);
    expect($cabinet->remainingFor('documents'))->toBe(3);

    $cabinet->meter('documents', 9);
    expect($cabinet->remainingFor('documents'))->toBe(0);
});

it('returns null remaining for an unlimited module', function (): void {
    $cabinet = cabinet();
    subscribe($cabinet, planWith(['documents' => ['included' => null]]));

    expect($cabinet->remainingFor('documents'))->toBeNull();
});

it('announces each threshold once per period', function (): void {
    Event::fake([UsageThresholdReached::class]);

    $cabinet = cabinet();
    $subscription = subscribe($cabinet, planWith(['documents' => ['included' => 10, 'price' => 1]]));

    $cabinet->meter('documents', 8);
    $cabinet->meter('documents', 1);
    $cabinet->meter('documents', 5);

    Event::assertDispatchedTimes(UsageThresholdReached::class, 2);

    expect(
        $subscription->events()->where('type', SubscriptionEventType::UsageThreshold)->count()
    )->toBe(2);
});

it('rebuilds counters from the source table, discarding drift', function (): void {
    $cabinet = cabinet();
    $subscription = subscribe($cabinet, planWith(['documents' => ['included' => 100, 'price' => 1]]));
    $alpha = customer($cabinet, 'Alpha');

    document($cabinet, $alpha);
    document($cabinet, $alpha);
    document($cabinet, $alpha, failed: true);

    $cabinet->meter('documents', 99, $alpha);
    expect($cabinet->usageFor('documents'))->toBe(99);

    app(UsageSynchronizer::class)->summarise($subscription, Period::current());

    expect($cabinet->usageFor('documents'))->toBe(2);
});

it('ignores usage recorded outside the period being summarised', function (): void {
    $cabinet = cabinet();
    $subscription = subscribe($cabinet, planWith(['documents' => ['included' => 100, 'price' => 1]]));

    document($cabinet, at: now()->startOfMonth()->addDay()->toDateTimeString());
    document($cabinet, at: now()->subMonthNoOverflow()->startOfMonth()->addDay()->toDateTimeString());

    $summary = app(UsageSynchronizer::class)->summarise($subscription, Period::current());

    expect($summary->get('documents')->total)->toBe(1);
});

it('collapses a snapshot module to one figure', function (): void {
    $cabinet = cabinet();
    $subscription = subscribe($cabinet, planWith(['seats' => ['included' => 2, 'price' => 50]]));

    customer($cabinet, 'Alpha');
    customer($cabinet, 'Beta');
    customer($cabinet, 'Gamma', active: false);

    $summary = app(UsageSynchronizer::class)->summarise($subscription, Period::current());

    expect($summary->get('seats')->total)->toBe(2)
        ->and($summary->get('seats')->overageQuantity())->toBe(0);
});

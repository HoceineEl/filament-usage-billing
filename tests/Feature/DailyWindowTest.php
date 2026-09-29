<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use HoceineEl\UsageBilling\Enums\GateReason;
use HoceineEl\UsageBilling\Enums\ResetPeriod;
use HoceineEl\UsageBilling\Enums\SubscriptionEventType;
use HoceineEl\UsageBilling\Services\InvoiceBuilder;
use HoceineEl\UsageBilling\Support\Period;
use HoceineEl\UsageBilling\Tests\Fixtures\Cabinet;

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-03-10 12:00:00'));
});

afterEach(function (): void {
    Cabinet::$timezone = null;
});

it('blocks a daily module once today is used up', function (): void {
    $cabinet = cabinet();
    subscribe($cabinet, planWith(['messages' => ['included' => 2]]));

    message($cabinet, '2026-03-09 10:00:00');
    message($cabinet, '2026-03-09 11:00:00');
    message($cabinet);

    expect($cabinet->canUse('messages'))->toBeTrue()
        ->and($cabinet->usageFor('messages'))->toBe(1)
        ->and($cabinet->remainingFor('messages'))->toBe(1);

    message($cabinet);
    $cabinet->meter('messages');

    expect($cabinet->moduleGate('messages')->reason)->toBe(GateReason::CeilingReached);
});

it('refills the allowance the next day', function (): void {
    $cabinet = cabinet();
    subscribe($cabinet, planWith(['messages' => ['included' => 1]]));
    message($cabinet);

    expect($cabinet->canUse('messages'))->toBeFalse();

    $this->travelTo(CarbonImmutable::parse('2026-03-11 00:30:00'));

    expect($cabinet->canUse('messages'))->toBeTrue();
});

it('rolls the day over at the subscriber\'s own midnight', function (): void {
    Cabinet::$timezone = 'Asia/Tokyo';
    $cabinet = cabinet();
    subscribe($cabinet, planWith(['messages' => ['included' => 1]]));

    message($cabinet, '2026-03-10 14:00:00');
    $this->travelTo(CarbonImmutable::parse('2026-03-10 16:00:00'));

    expect($cabinet->usageWindow('messages')->key)->toBe('2026-03-11')
        ->and($cabinet->canUse('messages'))->toBeTrue();
});

it('keeps monthly modules on the calendar month', function (): void {
    $cabinet = cabinet();
    subscribe($cabinet, planWith(['documents' => ['included' => 5]]));

    expect($cabinet->usageWindow('documents')->resetPeriod)->toBe(ResetPeriod::Month)
        ->and($cabinet->usageWindow('documents')->key)->toBe('2026-03');
});

it('bills overage day by day on the monthly invoice', function (): void {
    $cabinet = cabinet();
    $subscription = subscribe($cabinet, planWith(['messages' => ['included' => 2, 'price' => 1]], basePrice: 0));

    foreach (['2026-02-03', '2026-02-03', '2026-02-03', '2026-02-04', '2026-02-05', '2026-02-05'] as $day) {
        message($cabinet, "{$day} 09:00:00");
    }

    $summary = $cabinet->usageSummary('2026-02')->get('messages');
    $invoice = app(InvoiceBuilder::class)->build($subscription, Period::fromKey('2026-02'));

    expect($summary->total)->toBe(6)
        ->and($summary->overageQuantity())->toBe(1)
        ->and($summary->consumedPercentage())->toBeNull()
        ->and((float) $invoice->subtotal_ht)->toBe(1.0);
});

it('warns about a daily threshold once per day', function (): void {
    $cabinet = cabinet();
    $subscription = subscribe($cabinet, planWith(['messages' => ['included' => 2, 'price' => 1]]));

    message($cabinet);
    message($cabinet);
    $cabinet->meter('messages', 2);
    message($cabinet);
    $cabinet->meter('messages');

    $events = $subscription->events()->ofType(SubscriptionEventType::UsageThreshold)->get();

    expect($events)->toHaveCount(2)
        ->and($events->pluck('meta.period')->unique()->all())->toBe(['2026-03-10']);
});

it('describes a daily allowance per day', function (): void {
    $plan = planWith(['messages' => ['included' => 50]]);

    expect($plan->pricingFor('messages')->summaryLabel())->toBe('50 max / day');
});

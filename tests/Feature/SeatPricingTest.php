<?php

declare(strict_types=1);

use HoceineEl\UsageBilling\Enums\SubscriptionStatus;
use HoceineEl\UsageBilling\Models\Plan;
use HoceineEl\UsageBilling\Models\PlanModule;
use HoceineEl\UsageBilling\Services\SubscriptionManager;
use HoceineEl\UsageBilling\Services\TermBiller;

function seatPlan(float $seatPrice = 300, int $minSeats = 5, int $documentsPerSeat = 50): Plan
{
    $plan = annualPlan([
        'seats' => ['included' => 0],
        'documents' => ['included' => $documentsPerSeat],
    ], price: 0);

    $plan->forceFill([
        'seat_module' => 'seats',
        'seat_price_ht' => $seatPrice,
        'min_seats' => $minSeats,
    ])->save();

    $plan->planModules->each(function (PlanModule $row): void {
        if ($row->module->key === 'documents') {
            $row->forceFill(['settings' => ['per_seat' => true]])->save();
        }
    });

    return $plan->refresh()->load('planModules.module');
}

it('bills a seat term as seats times the seat price', function (): void {
    $cabinet = cabinet();
    $subscription = app(SubscriptionManager::class)->start($cabinet, seatPlan(), withTrial: false, seats: 8);

    $invoice = app(TermBiller::class)->issueTermInvoice($subscription);

    expect($subscription->seats)->toBe(8)
        ->and((float) $invoice->subtotal_ht)->toBe(2400.0)
        ->and($invoice->lines)->toHaveCount(1)
        ->and($invoice->lines->first()->quantity)->toEqual(8);
});

it('never bills fewer seats than the plan minimum', function (): void {
    $cabinet = cabinet();
    $subscription = app(SubscriptionManager::class)->start($cabinet, seatPlan(), withTrial: false, seats: 2);

    $invoice = app(TermBiller::class)->issueTermInvoice($subscription);

    expect($subscription->seats)->toBe(5)
        ->and((float) $invoice->subtotal_ht)->toBe(1500.0);
});

it('caps the seat module at the seats paid for and scales per-seat allowances', function (): void {
    $cabinet = cabinet();
    $subscription = app(SubscriptionManager::class)->start($cabinet, seatPlan(), withTrial: false, seats: 6);
    settle(app(TermBiller::class)->issueTermInvoice($subscription));
    $cabinet->forgetSubscription();

    expect($cabinet->allowanceFor('seats'))->toBe(6)
        ->and($cabinet->allowanceFor('documents'))->toBe(300);

    foreach (range(1, 6) as $index) {
        customer($cabinet, "Client {$index}");
    }

    expect($cabinet->canUse('seats'))->toBeFalse();
});

it('grants extra seats only once their prorated facture is paid', function (): void {
    $cabinet = cabinet();
    $subscription = app(SubscriptionManager::class)->start($cabinet, seatPlan(), withTrial: false, seats: 5);
    settle(app(TermBiller::class)->issueTermInvoice($subscription));

    $this->travel(6)->months();

    $invoice = app(TermBiller::class)->issueSeatInvoice($subscription->refresh(), 3);

    expect((float) $invoice->subtotal_ht)->toBeGreaterThan(400.0)->toBeLessThan(500.0)
        ->and($subscription->refresh()->seats)->toBe(5);

    settle($invoice);

    expect($subscription->refresh()->seats)->toBe(8)
        ->and($subscription->status)->toBe(SubscriptionStatus::Active);
});

it('applies a seat reduction at renewal, never to the paid term', function (): void {
    $cabinet = cabinet();
    $subscription = app(SubscriptionManager::class)->start($cabinet, seatPlan(), withTrial: false, seats: 10);
    settle(app(TermBiller::class)->issueTermInvoice($subscription));

    app(SubscriptionManager::class)->scheduleRenewalSeats($subscription->refresh(), 7);

    expect($subscription->refresh()->seats)->toBe(10)
        ->and($subscription->renewal_seats)->toBe(7);

    $renewal = app(TermBiller::class)->issueTermInvoice($subscription);

    expect((float) $renewal->subtotal_ht)->toBe(2100.0);

    settle($renewal);

    expect($subscription->refresh()->seats)->toBe(7)
        ->and($subscription->renewal_seats)->toBeNull();
});

it('prices an upgrade from a seat plan to a flat plan against the seats held', function (): void {
    $cabinet = cabinet();
    $subscription = app(SubscriptionManager::class)->start($cabinet, seatPlan(), withTrial: false, seats: 10);
    settle(app(TermBiller::class)->issueTermInvoice($subscription));

    $flat = annualPlan(['seats' => ['included' => 30]], price: 6900);
    $invoice = app(TermBiller::class)->issueUpgradeInvoice($subscription->refresh(), $flat);

    expect((float) $invoice->subtotal_ht)->toBeGreaterThan(3800.0)->toBeLessThanOrEqual(3900.0);
});

it('leaves flat plans priced as before', function (): void {
    $plan = annualPlan(price: 2490);

    expect($plan->isSeatBased())->toBeFalse()
        ->and($plan->termPriceFor(40))->toBe(2490.0);
});

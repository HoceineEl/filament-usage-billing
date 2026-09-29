<?php

declare(strict_types=1);

use HoceineEl\UsageBilling\Actions\ExtendTrialAction;
use HoceineEl\UsageBilling\Actions\GrantBillingGraceAction;
use HoceineEl\UsageBilling\Enums\InvoiceStatus;
use HoceineEl\UsageBilling\Enums\SubscriptionStatus;
use HoceineEl\UsageBilling\Services\TermBiller;
use HoceineEl\UsageBilling\Tests\Fixtures\Cabinet;

afterEach(function (): void {
    Cabinet::$limitsEnforced = true;
});

it('extends a trial and pushes its facture due date with it', function (): void {
    $subscription = subscribe(cabinet(), annualPlan(), [
        'status' => SubscriptionStatus::PastDue,
        'trial_ends_at' => now()->subDays(2),
        'grace_ends_at' => now()->subDay(),
    ]);
    $invoice = app(TermBiller::class)->issueTermInvoice($subscription);
    $invoice->forceFill(['due_at' => now()->subDays(2), 'status' => InvoiceStatus::Overdue])->save();

    $extended = app(ExtendTrialAction::class)->execute($subscription, now()->addDays(10));

    $subscription->refresh();
    $invoice->refresh();

    expect($extended)->toBeTrue()
        ->and($subscription->status)->toBe(SubscriptionStatus::Trialing)
        ->and($subscription->grace_ends_at)->toBeNull()
        ->and($subscription->isUsable())->toBeTrue()
        ->and($invoice->due_at->toDateString())->toBe(now()->addDays(10)->toDateString())
        ->and($invoice->status)->toBe(InvoiceStatus::Issued);
});

it('does not extend a trial once a term has been paid for', function (): void {
    $subscription = subscribe(cabinet(), annualPlan(), ['status' => SubscriptionStatus::PendingPayment]);
    settle(app(TermBiller::class)->issueTermInvoice($subscription));

    expect(app(ExtendTrialAction::class)->execute($subscription->refresh(), now()->addDays(10)))->toBeFalse();
});

it('reopens a locked subscription for a grace window', function (): void {
    $cabinet = cabinet();
    $subscription = subscribe($cabinet, annualPlan(), [
        'status' => SubscriptionStatus::Active,
        'ends_at' => now()->subDays(20),
        'grace_ends_at' => now()->subDays(10),
    ]);

    $action = app(GrantBillingGraceAction::class);

    expect($action->isLocked($subscription))->toBeTrue()
        ->and($action->execute($subscription, days: 5))->toBeTrue();

    $subscription->refresh();

    expect($subscription->status)->toBe(SubscriptionStatus::PastDue)
        ->and($subscription->grace_ends_at->toDateString())->toBe(now()->addDays(5)->toDateString())
        ->and($subscription->isUsable())->toBeTrue()
        ->and($action->isLocked($subscription))->toBeFalse();
});

it('has nothing to unlock while enforcement is off', function (): void {
    Cabinet::$limitsEnforced = false;
    $subscription = subscribe(cabinet(), annualPlan(), ['status' => SubscriptionStatus::PendingPayment]);

    expect(app(GrantBillingGraceAction::class)->isLocked($subscription))->toBeFalse();
});

<?php

declare(strict_types=1);

use HoceineEl\UsageBilling\Enums\InvoiceStatus;
use HoceineEl\UsageBilling\Enums\PaymentMethod;
use HoceineEl\UsageBilling\Enums\SubscriptionStatus;
use HoceineEl\UsageBilling\Models\Invoice;
use HoceineEl\UsageBilling\Services\SubscriptionManager;
use HoceineEl\UsageBilling\Services\TermBiller;

it('starts a new subscription awaiting payment, not active', function (): void {
    $cabinet = cabinet();
    $subscription = subscribe($cabinet, annualPlan(), ['status' => SubscriptionStatus::PendingPayment]);

    expect($subscription->status)->toBe(SubscriptionStatus::PendingPayment)
        ->and($subscription->isUsable())->toBeFalse()
        ->and($cabinet->canUse('documents'))->toBeFalse();
});

it('issues a term invoice for the whole year', function (): void {
    $cabinet = cabinet();
    $subscription = subscribe($cabinet, annualPlan(price: 2490), ['status' => SubscriptionStatus::PendingPayment]);

    $invoice = app(TermBiller::class)->issueTermInvoice($subscription);

    expect($invoice)->toBeInstanceOf(Invoice::class)
        ->and($invoice->status)->toBe(InvoiceStatus::Issued)
        ->and($invoice->number)->not->toBeNull()
        ->and((float) $invoice->subtotal_ht)->toBe(2490.0)
        ->and((float) $invoice->total_ttc)->toBe(2988.0)
        ->and($invoice->lines)->toHaveCount(1);
});

it('grants access for a full year once the term invoice is paid', function (): void {
    $cabinet = cabinet();
    $subscription = subscribe($cabinet, annualPlan(), ['status' => SubscriptionStatus::PendingPayment]);

    settle(app(TermBiller::class)->issueTermInvoice($subscription));

    $subscription->refresh();
    $cabinet->forgetSubscription();

    expect($subscription->status)->toBe(SubscriptionStatus::Active)
        ->and($subscription->ends_at?->toDateString())
        ->toBe(now()->startOfDay()->addYear()->subDay()->toDateString())
        ->and($cabinet->canUse('documents'))->toBeTrue();
});

it('does not grant access on a partial payment', function (): void {
    $cabinet = cabinet();
    $subscription = subscribe($cabinet, annualPlan(), ['status' => SubscriptionStatus::PendingPayment]);

    $invoice = app(TermBiller::class)->issueTermInvoice($subscription);
    $manager = app(SubscriptionManager::class);

    $manager->validatePayment($manager->declarePayment($invoice, [
        'method' => PaymentMethod::Virement,
        'amount' => 100,
    ]));

    expect($subscription->refresh()->status)->toBe(SubscriptionStatus::PendingPayment)
        ->and($subscription->isUsable())->toBeFalse();
});

it('does not bill the same term twice', function (): void {
    $cabinet = cabinet();
    $subscription = subscribe($cabinet, annualPlan(), ['status' => SubscriptionStatus::PendingPayment]);

    $first = app(TermBiller::class)->issueTermInvoice($subscription);
    $second = app(TermBiller::class)->issueTermInvoice($subscription);

    expect($second->getKey())->toBe($first->getKey())
        ->and(Invoice::count())->toBe(1);
});

it('starts the renewal term the day after the current one ends', function (): void {
    $cabinet = cabinet();
    $subscription = subscribe($cabinet, annualPlan(), ['status' => SubscriptionStatus::PendingPayment]);

    settle(app(TermBiller::class)->issueTermInvoice($subscription));
    $subscription->refresh();

    $firstEnd = $subscription->ends_at;
    settle(app(TermBiller::class)->issueTermInvoice($subscription));

    expect($subscription->refresh()->ends_at?->toDateString())
        ->toBe($firstEnd->addDay()->addYear()->subDay()->toDateString());
});

it('invoices a term that is inside its renewal notice window', function (): void {
    $cabinet = cabinet();
    $subscription = subscribe($cabinet, annualPlan(), ['status' => SubscriptionStatus::PendingPayment]);
    settle(app(TermBiller::class)->issueTermInvoice($subscription));

    $subscription->refresh()->forceFill(['ends_at' => now()->addDays(10)])->save();

    $this->artisan('usage-billing:issue-renewals')->assertSuccessful();

    expect(Invoice::count())->toBe(2);
});

it('leaves a term alone while its renewal is still far off', function (): void {
    $cabinet = cabinet();
    $subscription = subscribe($cabinet, annualPlan(), ['status' => SubscriptionStatus::PendingPayment]);
    settle(app(TermBiller::class)->issueTermInvoice($subscription));

    $this->artisan('usage-billing:issue-renewals')->assertSuccessful();

    expect(Invoice::count())->toBe(1);
});

it('walks an unrenewed term through grace and into expiry', function (): void {
    $cabinet = cabinet();
    $subscription = subscribe($cabinet, annualPlan(), ['status' => SubscriptionStatus::PendingPayment]);
    settle(app(TermBiller::class)->issueTermInvoice($subscription));

    $subscription->refresh()->forceFill(['ends_at' => now()->subDay()])->save();

    $this->artisan('usage-billing:expire-subscriptions')->assertSuccessful();
    $subscription->refresh();

    expect($subscription->status)->toBe(SubscriptionStatus::PastDue)
        ->and($subscription->isUsable())->toBeTrue();

    $subscription->forceFill(['grace_ends_at' => now()->subDay()])->save();
    $this->artisan('usage-billing:expire-subscriptions')->assertSuccessful();

    expect($subscription->refresh()->status)->toBe(SubscriptionStatus::Expired);
});

it('refuses access the moment a paid term runs out with no grace left', function (): void {
    $cabinet = cabinet();
    $subscription = subscribe($cabinet, annualPlan(), ['status' => SubscriptionStatus::PendingPayment]);
    settle(app(TermBiller::class)->issueTermInvoice($subscription));

    $subscription->refresh()->forceFill([
        'ends_at' => now()->subDay(),
        'grace_ends_at' => now()->subHour(),
    ])->save();
    $cabinet->forgetSubscription();

    expect($subscription->isUsable())->toBeFalse()
        ->and($cabinet->canUse('documents'))->toBeFalse();
});

it('prorates an upgrade to the months left in the term', function (): void {
    $cabinet = cabinet();
    $subscription = subscribe($cabinet, annualPlan(price: 1000), ['status' => SubscriptionStatus::PendingPayment]);
    settle(app(TermBiller::class)->issueTermInvoice($subscription));
    $subscription->refresh();

    // Half the year gone, half to run: the 1000 difference should cost ~500.
    // Both ends have to move, or the term is 18 months long.
    $subscription->forceFill([
        'starts_at' => now()->subMonths(6),
        'ends_at' => now()->addMonths(6),
    ])->save();

    $bigger = annualPlan(['documents' => ['included' => 500, 'price' => 1]], price: 2000);
    $invoice = app(TermBiller::class)->issueUpgradeInvoice($subscription->refresh(), $bigger);

    expect((float) $invoice->subtotal_ht)->toBeGreaterThan(400.0)
        ->and((float) $invoice->subtotal_ht)->toBeLessThan(600.0);
});

it('applies the new plan only once the upgrade is paid', function (): void {
    $cabinet = cabinet();
    $plan = annualPlan(['documents' => ['included' => 10, 'price' => 1]], price: 1000);
    $subscription = subscribe($cabinet, $plan, ['status' => SubscriptionStatus::PendingPayment]);
    settle(app(TermBiller::class)->issueTermInvoice($subscription));
    $subscription->refresh();

    $bigger = annualPlan(['documents' => ['included' => 900, 'price' => 1]], price: 2000);
    $invoice = app(TermBiller::class)->issueUpgradeInvoice($subscription, $bigger);

    $cabinet->forgetSubscription();
    expect($cabinet->currentSubscription()->plan_id)->toBe($plan->getKey());

    settle($invoice);
    $cabinet->forgetSubscription();

    expect($cabinet->currentSubscription()->plan_id)->toBe($bigger->getKey())
        ->and($cabinet->allowanceFor('documents'))->toBe(900);
});

it('never invoices a downgrade', function (): void {
    $cabinet = cabinet();
    $subscription = subscribe($cabinet, annualPlan(price: 2000), ['status' => SubscriptionStatus::PendingPayment]);
    settle(app(TermBiller::class)->issueTermInvoice($subscription));

    $smaller = annualPlan(['documents' => ['included' => 5, 'price' => 1]], price: 500);

    expect(app(TermBiller::class)->issueUpgradeInvoice($subscription->refresh(), $smaller))->toBeNull();
});

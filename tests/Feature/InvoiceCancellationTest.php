<?php

declare(strict_types=1);

use HoceineEl\UsageBilling\Actions\CancelInvoiceAction;
use HoceineEl\UsageBilling\Enums\InvoiceStatus;
use HoceineEl\UsageBilling\Enums\PaymentMethod;
use HoceineEl\UsageBilling\Enums\SubscriptionEventType;
use HoceineEl\UsageBilling\Enums\SubscriptionStatus;
use HoceineEl\UsageBilling\Services\SubscriptionManager;
use HoceineEl\UsageBilling\Services\TermBiller;

it('annuls an unpaid facture but keeps its number', function (): void {
    $subscription = subscribe(cabinet(), annualPlan(), ['status' => SubscriptionStatus::PendingPayment]);
    $invoice = app(TermBiller::class)->issueTermInvoice($subscription);

    expect(app(CancelInvoiceAction::class)->execute($invoice, 'Issued in error'))->toBeTrue();

    $invoice->refresh();

    expect($invoice->status)->toBe(InvoiceStatus::Cancelled)
        ->and($invoice->number)->not->toBeNull()
        ->and(json_decode($invoice->notes, true)['cancellation']['reason'])->toBe('Issued in error')
        ->and(TermBiller::termOf($invoice))->not->toBeNull()
        ->and($subscription->events()->ofType(SubscriptionEventType::InvoiceCancelled)->exists())->toBeTrue();
});

it('refuses to annul a facture with a declared payment', function (): void {
    $subscription = subscribe(cabinet(), annualPlan(), ['status' => SubscriptionStatus::PendingPayment]);
    $invoice = app(TermBiller::class)->issueTermInvoice($subscription);

    app(SubscriptionManager::class)->declarePayment($invoice, ['method' => PaymentMethod::Virement, 'amount' => 10]);

    expect(app(CancelInvoiceAction::class)->canCancel($invoice))->toBeFalse()
        ->and(app(CancelInvoiceAction::class)->execute($invoice, 'x'))->toBeFalse()
        ->and($invoice->refresh()->status)->toBe(InvoiceStatus::Issued);
});

it('lifts arrears once the only unpaid facture is annulled', function (): void {
    $subscription = subscribe(cabinet(), annualPlan(), ['status' => SubscriptionStatus::PendingPayment]);
    settle(app(TermBiller::class)->issueTermInvoice($subscription));

    $renewal = app(TermBiller::class)->issueTermInvoice($subscription->refresh());
    $subscription->forceFill(['status' => SubscriptionStatus::PastDue, 'grace_ends_at' => now()->addDay()])->save();

    app(CancelInvoiceAction::class)->execute($renewal, 'Duplicate');

    expect($subscription->refresh()->status)->toBe(SubscriptionStatus::Active)
        ->and($subscription->grace_ends_at)->toBeNull();
});

it('puts an unpaid trial back on trial when its facture is annulled', function (): void {
    $subscription = subscribe(cabinet(), annualPlan(), [
        'status' => SubscriptionStatus::PastDue,
        'trial_ends_at' => now()->addDays(3),
        'grace_ends_at' => now()->addDays(5),
    ]);
    $invoice = app(TermBiller::class)->issueTermInvoice($subscription);

    app(CancelInvoiceAction::class)->execute($invoice, 'Wrong plan');

    expect($subscription->refresh()->status)->toBe(SubscriptionStatus::Trialing);
});

<?php

declare(strict_types=1);

use HoceineEl\UsageBilling\Enums\InvoiceStatus;
use HoceineEl\UsageBilling\Enums\PaymentMethod;
use HoceineEl\UsageBilling\Enums\SubscriptionStatus;
use HoceineEl\UsageBilling\Services\InvoiceBuilder;
use HoceineEl\UsageBilling\Services\SubscriptionManager;
use HoceineEl\UsageBilling\Support\Period;

function issuedInvoice(float $basePrice = 500): array
{
    $cabinet = cabinet();
    $subscription = subscribe($cabinet, planWith(['documents' => ['included' => 10, 'price' => 1]], basePrice: $basePrice));
    $invoice = app(InvoiceBuilder::class)->issue(
        app(InvoiceBuilder::class)->build($subscription, Period::previous())
    );

    return [$cabinet, $subscription, $invoice];
}

it('leaves the invoice untouched until a declared payment is validated', function (): void {
    [, , $invoice] = issuedInvoice();

    app(SubscriptionManager::class)->declarePayment($invoice, [
        'method' => PaymentMethod::Virement,
        'amount' => 600,
    ]);

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Issued)
        ->and((float) $invoice->fresh()->amount_paid)->toBe(0.0);
});

it('marks an invoice partially paid then paid as payments land', function (): void {
    [, , $invoice] = issuedInvoice();
    $manager = app(SubscriptionManager::class);

    $first = $manager->declarePayment($invoice, ['method' => PaymentMethod::Virement, 'amount' => 200]);
    $manager->validatePayment($first);

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::PartiallyPaid);

    $second = $manager->declarePayment($invoice, ['method' => PaymentMethod::Virement, 'amount' => 400]);
    $manager->validatePayment($second);

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Paid)
        ->and($invoice->fresh()->paid_at)->not->toBeNull();
});

it('reverses the paid amount when a payment is rejected', function (): void {
    [, , $invoice] = issuedInvoice();
    $manager = app(SubscriptionManager::class);

    $payment = $manager->declarePayment($invoice, ['method' => PaymentMethod::Cheque, 'amount' => 600]);
    $manager->validatePayment($payment);
    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Paid);

    $manager->rejectPayment($payment, 'Cheque returned');

    expect((float) $invoice->fresh()->amount_paid)->toBe(0.0)
        ->and($invoice->fresh()->status)->not->toBe(InvoiceStatus::Paid);
});

it('moves an overdue invoice into the grace window', function (): void {
    [, $subscription, $invoice] = issuedInvoice();
    $invoice->forceFill(['due_at' => now()->subDay()])->save();

    $this->artisan('usage-billing:mark-overdue')->assertSuccessful();

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Overdue)
        ->and($subscription->fresh()->status)->toBe(SubscriptionStatus::PastDue)
        ->and($subscription->fresh()->grace_ends_at)->not->toBeNull();
});

it('restores access once the arrears are settled', function (): void {
    [, $subscription, $invoice] = issuedInvoice();
    $invoice->forceFill(['due_at' => now()->subDay()])->save();
    $this->artisan('usage-billing:mark-overdue')->assertSuccessful();

    $manager = app(SubscriptionManager::class);
    $payment = $manager->declarePayment($invoice, ['method' => PaymentMethod::Virement, 'amount' => 600]);
    $manager->validatePayment($payment);

    expect($subscription->fresh()->status)->toBe(SubscriptionStatus::Active)
        ->and($subscription->fresh()->grace_ends_at)->toBeNull();
});

it('keeps the subscription in arrears while an older invoice is still unpaid', function (): void {
    [, $subscription, $first] = issuedInvoice();
    $first->forceFill(['due_at' => now()->subDay()])->save();
    $this->artisan('usage-billing:mark-overdue')->assertSuccessful();

    $second = app(InvoiceBuilder::class)->issue(
        app(InvoiceBuilder::class)->build($subscription, Period::previous()->prior())
    );

    $manager = app(SubscriptionManager::class);
    $manager->validatePayment(
        $manager->declarePayment($second, ['method' => PaymentMethod::Virement, 'amount' => (float) $second->total_ttc])
    );

    expect($subscription->fresh()->status)->toBe(SubscriptionStatus::PastDue);
});

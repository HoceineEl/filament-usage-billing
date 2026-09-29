<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use HoceineEl\UsageBilling\Enums\InvoiceStatus;
use HoceineEl\UsageBilling\Enums\SubscriptionStatus;
use HoceineEl\UsageBilling\Filament\Resources\Invoices\Pages\ListInvoices;
use HoceineEl\UsageBilling\Filament\Resources\Subscriptions\Pages\ListSubscriptions;
use HoceineEl\UsageBilling\Services\TermBiller;
use HoceineEl\UsageBilling\Tests\Fixtures\User;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->actingAs(User::create(['name' => 'Operator', 'email' => 'ops@example.test', 'password' => 'secret']));
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('cancels an invoice from the admin table', function (): void {
    $invoice = app(TermBiller::class)->issueTermInvoice(subscribe(cabinet(), annualPlan(), ['status' => SubscriptionStatus::PendingPayment]));

    Livewire::test(ListInvoices::class)
        ->callTableAction('cancelInvoice', $invoice, ['reason' => 'Issued twice'])
        ->assertHasNoTableActionErrors();

    expect($invoice->refresh()->status)->toBe(InvoiceStatus::Cancelled);
});

it('cancels invoices in bulk', function (): void {
    $first = app(TermBiller::class)->issueTermInvoice(subscribe(cabinet(), annualPlan(), ['status' => SubscriptionStatus::PendingPayment]));
    $second = app(TermBiller::class)->issueTermInvoice(subscribe(cabinet(['name' => 'B']), annualPlan(), ['status' => SubscriptionStatus::PendingPayment]));

    Livewire::test(ListInvoices::class)
        ->callTableBulkAction('cancelInvoices', [$first, $second], ['reason' => 'Test run']);

    expect($first->refresh()->status)->toBe(InvoiceStatus::Cancelled)
        ->and($second->refresh()->status)->toBe(InvoiceStatus::Cancelled);
});

it('extends a trial from the admin table', function (): void {
    $subscription = subscribe(cabinet(), annualPlan(), [
        'status' => SubscriptionStatus::Trialing,
        'trial_ends_at' => now()->addDay(),
    ]);

    Livewire::test(ListSubscriptions::class)
        ->callTableAction('extendTrial', $subscription, ['trial_ends_at' => now()->addDays(20)->toDateString()])
        ->assertHasNoTableActionErrors();

    expect($subscription->refresh()->trial_ends_at->toDateString())->toBe(now()->addDays(20)->toDateString());
});

it('grants a grace period to a locked subscription from the admin table', function (): void {
    $subscription = subscribe(cabinet(), annualPlan(), ['status' => SubscriptionStatus::PendingPayment]);

    Livewire::test(ListSubscriptions::class)
        ->set('activeTab', 'all')
        ->assertTableActionVisible('grantGrace', $subscription)
        ->callTableAction('grantGrace', $subscription, ['days' => 7])
        ->assertHasNoTableActionErrors();

    expect($subscription->refresh()->isUsable())->toBeTrue();
});

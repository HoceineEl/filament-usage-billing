<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Filament\Pages\Dashboard;
use HoceineEl\UsageBilling\Enums\PaymentStatus;
use HoceineEl\UsageBilling\Enums\SubscriptionStatus;
use HoceineEl\UsageBilling\Filament\Pages\TenantBilling;
use HoceineEl\UsageBilling\Models\Payment;
use HoceineEl\UsageBilling\Services\TermBiller;
use HoceineEl\UsageBilling\Tests\Fixtures\Cabinet;
use HoceineEl\UsageBilling\Tests\Fixtures\TestPanelProvider;
use HoceineEl\UsageBilling\Tests\Fixtures\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

function member(Cabinet $cabinet, bool $owner = true): User
{
    return User::create([
        'cabinet_id' => $cabinet->getKey(),
        'name' => 'Member',
        'email' => 'member'.uniqid().'@example.test',
        'password' => 'secret',
        'is_owner' => $owner,
    ]);
}

function actInTenant(Cabinet $cabinet, bool $owner = true): User
{
    $user = member($cabinet, $owner);

    test()->actingAs($user);
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Filament::setTenant($cabinet);

    return $user;
}

afterEach(function (): void {
    TestPanelProvider::$ownerOnly = false;
    Cabinet::$limitsEnforced = true;
    config()->set('usage-billing.gateways.default', null);
});

it('registers only the billing page on a tenant panel and only resources on the admin panel', function (): void {
    expect(Filament::getPanel('app')->getPages())->toContain(TenantBilling::class)
        ->and(Filament::getPanel('app')->getResources())->toBeEmpty()
        ->and(Filament::getPanel('admin')->getResources())->not->toBeEmpty()
        ->and(Filament::getPanel('admin')->getPages())->not->toContain(TenantBilling::class);
});

it('shows the plan, usage and invoices of the current tenant only', function (): void {
    $cabinet = cabinet();
    $subscription = subscribe($cabinet, annualPlan(['documents' => ['included' => 10], 'messages' => ['included' => 5]]), ['status' => SubscriptionStatus::PendingPayment]);
    $invoice = app(TermBiller::class)->issueTermInvoice($subscription);
    document($cabinet);
    message($cabinet);

    $other = cabinet(['name' => 'Other']);
    $foreign = app(TermBiller::class)->issueTermInvoice(subscribe($other, annualPlan(), ['status' => SubscriptionStatus::PendingPayment]));

    actInTenant($cabinet);

    Livewire::test(TenantBilling::class)
        ->assertOk()
        ->assertSee($subscription->plan->displayName())
        ->assertSee('Documents')
        ->assertSee(__('usage-billing::billing.tenant.modules.today'))
        ->assertCanSeeTableRecords([$invoice])
        ->assertCanNotSeeTableRecords([$foreign]);
});

it('lets the subscriber declare a payment with a private receipt', function (): void {
    Storage::fake('local');
    $cabinet = cabinet();
    $invoice = app(TermBiller::class)->issueTermInvoice(subscribe($cabinet, annualPlan(), ['status' => SubscriptionStatus::PendingPayment]));
    $user = actInTenant($cabinet);

    Livewire::test(TenantBilling::class)
        ->callTableAction('declarePayment', $invoice, [
            'amount' => 100,
            'method' => 'virement',
            'paid_at' => now()->toDateString(),
            'reference' => 'VIR-1',
            'receipt_path' => UploadedFile::fake()->create('receipt.pdf', 10, 'application/pdf'),
        ])
        ->assertHasNoTableActionErrors();

    $payment = Payment::sole();

    expect($payment->status)->toBe(PaymentStatus::Pending)
        ->and($payment->submitted_by_id)->toBe($user->getKey())
        ->and($payment->receipt_path)->toStartWith('usage-billing/receipts/');

    Storage::disk('local')->assertExists($payment->receipt_path);

    Livewire::test(TenantBilling::class)
        ->callTableAction('downloadReceipt', $invoice)
        ->assertFileDownloaded();
});

it('sends the subscriber to the gateway to pay online', function (): void {
    config()->set('usage-billing.gateways.default', 'fake');
    $cabinet = cabinet();
    $invoice = app(TermBiller::class)->issueTermInvoice(subscribe($cabinet, annualPlan(), ['status' => SubscriptionStatus::PendingPayment]));
    actInTenant($cabinet);

    Livewire::test(TenantBilling::class)
        ->assertTableActionVisible('payOnline', $invoice)
        ->callTableAction('payOnline', $invoice)
        ->assertRedirect(url("usage-billing/fake-checkout/{$invoice->getKey()}"));
});

it('hides online payment while no gateway is configured', function (): void {
    $cabinet = cabinet();
    $invoice = app(TermBiller::class)->issueTermInvoice(subscribe($cabinet, annualPlan(), ['status' => SubscriptionStatus::PendingPayment]));
    actInTenant($cabinet);

    Livewire::test(TenantBilling::class)->assertTableActionHidden('payOnline', $invoice);
});

it('redirects a locked subscriber to the billing page', function (): void {
    $cabinet = cabinet();
    subscribe($cabinet, annualPlan(), ['status' => SubscriptionStatus::PendingPayment]);
    $this->actingAs(member($cabinet));

    $this->get(Dashboard::getUrl(panel: 'app', tenant: $cabinet))
        ->assertRedirect(TenantBilling::getUrl(panel: 'app', tenant: $cabinet));

    $this->get(TenantBilling::getUrl(panel: 'app', tenant: $cabinet))->assertOk();
});

it('lets a usable subscription through', function (): void {
    $cabinet = cabinet();
    subscribe($cabinet, annualPlan(), ['status' => SubscriptionStatus::Active]);
    $this->actingAs(member($cabinet));

    $this->get(Dashboard::getUrl(panel: 'app', tenant: $cabinet))->assertOk();
});

it('does not lock anyone while enforcement is off', function (): void {
    Cabinet::$limitsEnforced = false;
    $cabinet = cabinet();
    $this->actingAs(member($cabinet));

    $this->get(Dashboard::getUrl(panel: 'app', tenant: $cabinet))->assertOk();
});

it('refuses members who cannot pay instead of redirecting them', function (): void {
    TestPanelProvider::$ownerOnly = true;
    $cabinet = cabinet();
    subscribe($cabinet, annualPlan(), ['status' => SubscriptionStatus::PendingPayment]);
    $this->actingAs(member($cabinet, owner: false));

    $this->get(Dashboard::getUrl(panel: 'app', tenant: $cabinet))->assertForbidden();
    $this->get(TenantBilling::getUrl(panel: 'app', tenant: $cabinet))->assertForbidden();
});

it('reopens the panel once the term facture is paid', function (): void {
    $cabinet = cabinet();
    $subscription = subscribe($cabinet, annualPlan(), ['status' => SubscriptionStatus::PendingPayment]);
    settle(app(TermBiller::class)->issueTermInvoice($subscription));
    $this->actingAs(member($cabinet));

    $this->get(Dashboard::getUrl(panel: 'app', tenant: $cabinet))->assertOk();
});

it('prints bank details next to the outstanding facture', function (): void {
    config()->set('usage-billing.seller.rib', '011 780 0000123456789012 34');
    config()->set('usage-billing.seller.bank_name', 'Test Bank');
    $cabinet = cabinet();
    $invoice = app(TermBiller::class)->issueTermInvoice(subscribe($cabinet, annualPlan(), [
        'status' => SubscriptionStatus::Trialing,
        'trial_ends_at' => now()->addDays(5),
    ]));
    actInTenant($cabinet);

    Livewire::test(TenantBilling::class)
        ->assertSee('011 780 0000123456789012 34')
        ->assertSee('Test Bank')
        ->assertSee($invoice->number)
        ->assertSee(__('usage-billing::billing.tenant.term.trial_description'));
});

it('explains that there is no subscription yet', function (): void {
    actInTenant(cabinet());

    Livewire::test(TenantBilling::class)
        ->assertSee(__('usage-billing::billing.tenant.empty.heading'))
        ->assertSee(__('usage-billing::billing.tenant.locked.no_subscription'));
});

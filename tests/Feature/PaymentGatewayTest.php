<?php

declare(strict_types=1);

use HoceineEl\UsageBilling\Contracts\PaymentGateway;
use HoceineEl\UsageBilling\Data\GatewayPayment;
use HoceineEl\UsageBilling\Enums\InvoiceStatus;
use HoceineEl\UsageBilling\Enums\PaymentMethod;
use HoceineEl\UsageBilling\Enums\PaymentStatus;
use HoceineEl\UsageBilling\Enums\SubscriptionStatus;
use HoceineEl\UsageBilling\Gateways\FakeGateway;
use HoceineEl\UsageBilling\Models\Invoice;
use HoceineEl\UsageBilling\Models\Payment;
use HoceineEl\UsageBilling\Services\PaymentGatewayManager;
use HoceineEl\UsageBilling\Services\TermBiller;
use Illuminate\Http\Request;

function postWebhook(array $payload, ?string $signature = null, string $gateway = 'fake')
{
    $body = json_encode($payload);

    return test()->call('POST', route('usage-billing.webhooks', ['gateway' => $gateway]), server: [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_'.str_replace('-', '_', strtoupper(FakeGateway::SIGNATURE_HEADER)) => $signature ?? app(PaymentGatewayManager::class)->driver('fake')->signature($body),
    ], content: $body);
}

function termInvoice(): Invoice
{
    return app(TermBiller::class)->issueTermInvoice(
        subscribe(cabinet(), annualPlan(), ['status' => SubscriptionStatus::PendingPayment])
    );
}

it('stays offline-only until a default gateway is configured', function (): void {
    expect(app(PaymentGatewayManager::class)->enabled())->toBeFalse();

    config()->set('usage-billing.gateways.default', 'fake');

    expect(app(PaymentGatewayManager::class)->enabled())->toBeTrue()
        ->and(app(PaymentGatewayManager::class)->driver())->toBeInstanceOf(FakeGateway::class);
});

it('hands out a checkout url for an invoice', function (): void {
    $invoice = termInvoice();

    expect(app(PaymentGatewayManager::class)->driver('fake')->checkout($invoice))
        ->toEndWith("usage-billing/fake-checkout/{$invoice->getKey()}");
});

it('records and validates a payment from a verified webhook', function (): void {
    $invoice = termInvoice();

    postWebhook([
        'type' => 'payment.succeeded',
        'invoice_id' => $invoice->getKey(),
        'amount' => (float) $invoice->total_ttc,
        'reference' => 'ch_123',
    ])->assertOk()->assertJson(['status' => 'recorded']);

    $payment = Payment::sole();

    expect($payment->status)->toBe(PaymentStatus::Validated)
        ->and($payment->method)->toBe(PaymentMethod::Card)
        ->and($payment->notes)->toBe('gateway:fake')
        ->and($invoice->refresh()->status)->toBe(InvoiceStatus::Paid)
        ->and($invoice->subscription->status)->toBe(SubscriptionStatus::Active);
});

it('ignores a redelivered webhook', function (): void {
    $invoice = termInvoice();
    $payload = ['type' => 'payment.succeeded', 'invoice_id' => $invoice->getKey(), 'amount' => 10, 'reference' => 'ch_1'];

    postWebhook($payload)->assertOk();
    postWebhook($payload)->assertOk();

    expect(Payment::count())->toBe(1);
});

it('rejects a webhook with a bad signature', function (): void {
    $invoice = termInvoice();

    postWebhook(['type' => 'payment.succeeded', 'invoice_id' => $invoice->getKey(), 'amount' => 10, 'reference' => 'x'], 'forged')
        ->assertForbidden();

    expect(Payment::count())->toBe(0);
});

it('acknowledges events that confirm no payment', function (): void {
    postWebhook(['type' => 'checkout.opened'])->assertOk()->assertJson(['status' => 'ignored']);
});

it('answers 404 for an unknown gateway', function (): void {
    postWebhook(['type' => 'payment.succeeded'], gateway: 'nope')->assertNotFound();
});

it('lets an application register its own gateway', function (): void {
    app(PaymentGatewayManager::class)->extend('acme', fn (): PaymentGateway => new class implements PaymentGateway
    {
        public function checkout(Invoice $invoice): string
        {
            return 'https://acme.test/pay';
        }

        public function handleWebhook(Request $request): ?GatewayPayment
        {
            return new GatewayPayment($request->input('invoice'), 5.0, 'acme_1');
        }
    });

    $invoice = termInvoice();

    $this->postJson(route('usage-billing.webhooks', ['gateway' => 'acme']), ['invoice' => $invoice->getKey()])->assertOk();

    expect(Payment::sole()->reference)->toBe('acme_1');
});

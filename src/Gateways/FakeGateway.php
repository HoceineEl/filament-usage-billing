<?php

declare(strict_types=1);

namespace HoceineEl\UsageBilling\Gateways;

use Carbon\CarbonImmutable;
use HoceineEl\UsageBilling\Contracts\PaymentGateway;
use HoceineEl\UsageBilling\Data\GatewayPayment;
use HoceineEl\UsageBilling\Exceptions\InvalidWebhookSignatureException;
use HoceineEl\UsageBilling\Models\Invoice;
use Illuminate\Http\Request;

/**
 * A gateway that takes no money, for tests and local development. Webhooks
 * are signed with an HMAC of the raw body, the way real providers do it.
 */
class FakeGateway implements PaymentGateway
{
    public const SIGNATURE_HEADER = 'X-Usage-Billing-Signature';

    public function __construct(private readonly string $secret = 'fake') {}

    public function checkout(Invoice $invoice): string
    {
        return url("usage-billing/fake-checkout/{$invoice->getKey()}");
    }

    public function handleWebhook(Request $request): ?GatewayPayment
    {
        if (! hash_equals($this->signature($request->getContent()), (string) $request->header(self::SIGNATURE_HEADER))) {
            throw InvalidWebhookSignatureException::forGateway('fake');
        }

        if ($request->input('type') !== 'payment.succeeded') {
            return null;
        }

        return new GatewayPayment(
            invoiceId: $request->input('invoice_id'),
            amount: (float) $request->input('amount'),
            reference: (string) $request->input('reference'),
            paidAt: $request->filled('paid_at') ? CarbonImmutable::parse($request->input('paid_at')) : null,
        );
    }

    public function signature(string $payload): string
    {
        return hash_hmac('sha256', $payload, $this->secret);
    }
}

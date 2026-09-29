<?php

declare(strict_types=1);

namespace HoceineEl\UsageBilling\Contracts;

use HoceineEl\UsageBilling\Data\GatewayPayment;
use HoceineEl\UsageBilling\Exceptions\InvalidWebhookSignatureException;
use HoceineEl\UsageBilling\Models\Invoice;
use Illuminate\Http\Request;

/**
 * An online payment provider. The package only asks two things of it: where to
 * send a subscriber to pay a facture, and what a verified webhook says was
 * paid. Recording and validating the payment stays with the package.
 */
interface PaymentGateway
{
    /** The URL the subscriber is redirected to in order to pay the invoice. */
    public function checkout(Invoice $invoice): string;

    /**
     * Verify the webhook and describe the payment it confirms, or return null
     * for an event that confirms none.
     *
     * @throws InvalidWebhookSignatureException
     */
    public function handleWebhook(Request $request): ?GatewayPayment;
}

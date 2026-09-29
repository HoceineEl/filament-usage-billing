<?php

declare(strict_types=1);

namespace HoceineEl\UsageBilling\Http\Controllers;

use HoceineEl\UsageBilling\Exceptions\InvalidWebhookSignatureException;
use HoceineEl\UsageBilling\Models\Invoice;
use HoceineEl\UsageBilling\Services\PaymentGatewayManager;
use HoceineEl\UsageBilling\Services\SubscriptionManager;
use HoceineEl\UsageBilling\UsageBilling;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * Receives a gateway's webhook, lets the gateway verify it, then records the
 * payment through the same validation path an operator uses.
 */
class PaymentWebhookController
{
    public function __invoke(
        Request $request,
        string $gateway,
        PaymentGatewayManager $gateways,
        SubscriptionManager $subscriptions,
    ): JsonResponse {
        try {
            $driver = $gateways->driver($gateway);
        } catch (InvalidArgumentException) {
            abort(404);
        }

        try {
            $confirmed = $driver->handleWebhook($request);
        } catch (InvalidWebhookSignatureException) {
            abort(403);
        }

        if ($confirmed === null) {
            return response()->json(['status' => 'ignored']);
        }

        $invoice = UsageBilling::query('invoice')->find($confirmed->invoiceId);

        if (! $invoice instanceof Invoice) {
            abort(404);
        }

        $payment = $subscriptions->recordGatewayPayment($invoice, $confirmed, $gateway);

        return response()->json(['status' => 'recorded', 'payment' => $payment->getKey()]);
    }
}

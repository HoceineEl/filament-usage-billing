<?php

declare(strict_types=1);

use HoceineEl\UsageBilling\Http\Controllers\PaymentWebhookController;
use Illuminate\Support\Facades\Route;

Route::post(trim((string) config('usage-billing.gateways.webhook_path', 'usage-billing/webhooks'), '/').'/{gateway}', PaymentWebhookController::class)
    ->name('usage-billing.webhooks');

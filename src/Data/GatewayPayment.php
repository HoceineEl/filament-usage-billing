<?php

declare(strict_types=1);

namespace HoceineEl\UsageBilling\Data;

use Carbon\CarbonImmutable;
use HoceineEl\UsageBilling\Enums\PaymentMethod;

/**
 * What a verified gateway webhook confirms was paid. The reference is the
 * provider's own id for the charge, which makes a redelivered webhook a no-op.
 */
final readonly class GatewayPayment
{
    public function __construct(
        public int|string $invoiceId,
        public float $amount,
        public string $reference,
        public ?CarbonImmutable $paidAt = null,
        public PaymentMethod $method = PaymentMethod::Card,
    ) {}
}

<?php

declare(strict_types=1);

namespace HoceineEl\UsageBilling\Services;

use HoceineEl\UsageBilling\Contracts\PaymentGateway;
use HoceineEl\UsageBilling\Gateways\FakeGateway;
use Illuminate\Support\Manager;

/**
 * Resolves payment gateways by name. The package ships only the fake one;
 * applications register real providers with `extend()`.
 *
 * @method PaymentGateway driver(?string $driver = null)
 */
class PaymentGatewayManager extends Manager
{
    public function getDefaultDriver(): ?string
    {
        $driver = $this->config->get('usage-billing.gateways.default');

        return is_string($driver) && $driver !== '' ? $driver : null;
    }

    /** Whether subscribers can pay online at all. */
    public function enabled(): bool
    {
        return $this->getDefaultDriver() !== null;
    }

    protected function createFakeDriver(): PaymentGateway
    {
        return new FakeGateway((string) $this->config->get('usage-billing.gateways.drivers.fake.secret', 'fake'));
    }
}

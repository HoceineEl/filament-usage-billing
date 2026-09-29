<?php

declare(strict_types=1);

namespace HoceineEl\UsageBilling\Exceptions;

use RuntimeException;

class InvalidWebhookSignatureException extends RuntimeException
{
    public static function forGateway(string $gateway): self
    {
        return new self("The [{$gateway}] webhook signature could not be verified.");
    }
}

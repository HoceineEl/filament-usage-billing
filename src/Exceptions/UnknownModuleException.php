<?php

declare(strict_types=1);

namespace HoceineEl\UsageBilling\Exceptions;

use InvalidArgumentException;

class UnknownModuleException extends InvalidArgumentException
{
    public static function forKey(string $key): self
    {
        return new self(
            "No metered module is registered under the key [{$key}]. "
            .'Add its class to `usage-billing.modules` and run `usage-billing:sync-modules`.'
        );
    }

    public static function forClass(string $class): self
    {
        return new self(
            "[{$class}] is listed in `usage-billing.modules` but does not implement "
            .'HoceineEl\UsageBilling\Contracts\MeteredModule.'
        );
    }
}

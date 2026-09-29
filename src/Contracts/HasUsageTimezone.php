<?php

declare(strict_types=1);

namespace HoceineEl\UsageBilling\Contracts;

/**
 * Implemented by a subscriber whose daily allowances should roll over at its
 * own midnight rather than the application's.
 */
interface HasUsageTimezone
{
    public function usageTimezone(): string;
}

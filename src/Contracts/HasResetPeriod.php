<?php

declare(strict_types=1);

namespace HoceineEl\UsageBilling\Contracts;

use HoceineEl\UsageBilling\Enums\ResetPeriod;

/**
 * Implemented by a metered module whose allowance refills more often than
 * monthly. Modules without it reset on the calendar month.
 */
interface HasResetPeriod
{
    public function resetPeriod(): ResetPeriod;
}

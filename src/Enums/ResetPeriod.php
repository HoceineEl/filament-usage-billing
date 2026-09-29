<?php

declare(strict_types=1);

namespace HoceineEl\UsageBilling\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * How often a metered module's allowance refills. Invoicing stays monthly
 * whatever the window; only allowance and limit checks follow it.
 */
enum ResetPeriod: string implements HasLabel
{
    case Month = 'month';
    case Day = 'day';

    public function getLabel(): string
    {
        return __("usage-billing::billing.reset_period.{$this->value}");
    }
}

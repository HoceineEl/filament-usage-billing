<?php

declare(strict_types=1);

namespace HoceineEl\UsageBilling\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PaymentStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Validated = 'validated';
    case Rejected = 'rejected';

    public function getLabel(): string
    {
        return __("usage-billing::billing.payment_status.{$this->value}");
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Validated => 'success',
            self::Rejected => 'danger',
        };
    }
}

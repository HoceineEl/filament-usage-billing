<?php

declare(strict_types=1);

namespace HoceineEl\UsageBilling\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum SubscriptionStatus: string implements HasColor, HasLabel
{
    case PendingPayment = 'pending_payment';
    case Trialing = 'trialing';
    case Active = 'active';
    case PastDue = 'past_due';
    case Suspended = 'suspended';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    public function getLabel(): string
    {
        return __("usage-billing::billing.subscription_status.{$this->value}");
    }

    public function getColor(): string
    {
        return match ($this) {
            self::PendingPayment => 'warning',
            self::Trialing => 'info',
            self::Active => 'success',
            self::PastDue => 'warning',
            self::Suspended, self::Cancelled, self::Expired => 'danger',
        };
    }

    /**
     * Whether this status alone permits usage. PastDue is deliberately absent:
     * it depends on the grace window, which only the subscription knows.
     */
    public function grantsAccess(): bool
    {
        return in_array($this, [self::Trialing, self::Active], true);
    }
}

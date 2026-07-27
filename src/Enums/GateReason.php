<?php

declare(strict_types=1);

namespace HoceineEl\UsageBilling\Enums;

use Filament\Support\Contracts\HasLabel;

enum GateReason: string implements HasLabel
{
    case Allowed = 'allowed';
    case NoSubscription = 'no_subscription';
    case SubscriptionInactive = 'subscription_inactive';
    case ModuleNotInPlan = 'module_not_in_plan';
    case CeilingReached = 'ceiling_reached';

    public function getLabel(): string
    {
        return __("usage-billing::billing.gate_reason.{$this->value}");
    }

    /**
     * Whether the subscriber can fix this themselves by paying, as opposed to
     * needing a plan change.
     */
    public function isResolvedByPayment(): bool
    {
        return $this === self::SubscriptionInactive;
    }
}

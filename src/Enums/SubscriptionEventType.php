<?php

declare(strict_types=1);

namespace HoceineEl\UsageBilling\Enums;

use Filament\Support\Contracts\HasLabel;

enum SubscriptionEventType: string implements HasLabel
{
    case Created = 'created';
    case PlanChanged = 'plan_changed';
    case StatusChanged = 'status_changed';
    case Renewed = 'renewed';
    case Cancelled = 'cancelled';
    case UsageThreshold = 'usage_threshold';
    case InvoiceIssued = 'invoice_issued';
    case PaymentValidated = 'payment_validated';

    public function getLabel(): string
    {
        return __("usage-billing::billing.event_type.{$this->value}");
    }
}

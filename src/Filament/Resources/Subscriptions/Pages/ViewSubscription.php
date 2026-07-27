<?php

declare(strict_types=1);

namespace HoceineEl\UsageBilling\Filament\Resources\Subscriptions\Pages;

use Filament\Resources\Pages\ViewRecord;
use HoceineEl\UsageBilling\Filament\Resources\Subscriptions\SubscriptionResource;

class ViewSubscription extends ViewRecord
{
    protected static string $resource = SubscriptionResource::class;

    public function getTitle(): string
    {
        return SubscriptionResource::subscriberName($this->getRecord());
    }
}

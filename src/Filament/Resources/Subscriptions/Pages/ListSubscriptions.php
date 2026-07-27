<?php

declare(strict_types=1);

namespace HoceineEl\UsageBilling\Filament\Resources\Subscriptions\Pages;

use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use HoceineEl\UsageBilling\Enums\SubscriptionStatus;
use HoceineEl\UsageBilling\Filament\Resources\Subscriptions\SubscriptionResource;
use Illuminate\Database\Eloquent\Builder;

class ListSubscriptions extends ListRecords
{
    protected static string $resource = SubscriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            SubscriptionResource::startSubscriptionAction(),
        ];
    }

    /**
     * Arrears first: that tab is the one an operator opens to see who needs
     * chasing.
     */
    public function getTabs(): array
    {
        $needsAttention = [SubscriptionStatus::PastDue, SubscriptionStatus::Suspended];

        return [
            'attention' => Tab::make(__('usage-billing::billing.subscription.tabs.attention'))
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereIn('status', $needsAttention))
                ->badge(fn (): int => static::getResource()::getEloquentQuery()
                    ->whereIn('status', $needsAttention)
                    ->count())
                ->badgeColor('warning'),
            'active' => Tab::make(__('usage-billing::billing.subscription.tabs.active'))
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->usable()),
            'all' => Tab::make(__('usage-billing::billing.subscription.tabs.all')),
        ];
    }

    public function getDefaultActiveTab(): string
    {
        return 'active';
    }
}

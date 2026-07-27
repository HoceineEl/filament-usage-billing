<?php

declare(strict_types=1);

namespace HoceineEl\UsageBilling\Filament\Resources\Payments\Pages;

use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use HoceineEl\UsageBilling\Filament\Resources\Payments\PaymentResource;
use Illuminate\Database\Eloquent\Builder;

class ListPayments extends ListRecords
{
    protected static string $resource = PaymentResource::class;

    public function getTabs(): array
    {
        return [
            'pending' => Tab::make(__('usage-billing::billing.payment.tabs.pending'))
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->pending())
                ->badge(fn (): int => static::getResource()::getEloquentQuery()->pending()->count())
                ->badgeColor('warning'),
            'validated' => Tab::make(__('usage-billing::billing.payment.tabs.validated'))
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->validated()),
            'all' => Tab::make(__('usage-billing::billing.payment.tabs.all')),
        ];
    }

    public function getDefaultActiveTab(): string
    {
        return 'pending';
    }
}

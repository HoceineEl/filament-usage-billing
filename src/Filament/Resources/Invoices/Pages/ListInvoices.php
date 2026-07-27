<?php

declare(strict_types=1);

namespace HoceineEl\UsageBilling\Filament\Resources\Invoices\Pages;

use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use HoceineEl\UsageBilling\Enums\InvoiceStatus;
use HoceineEl\UsageBilling\Filament\Resources\Invoices\InvoiceResource;
use Illuminate\Database\Eloquent\Builder;

class ListInvoices extends ListRecords
{
    protected static string $resource = InvoiceResource::class;

    public function getTabs(): array
    {
        return [
            'outstanding' => Tab::make(__('usage-billing::billing.invoice.tabs.outstanding'))
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->awaitingPayment())
                ->badge(fn (): int => static::getResource()::getEloquentQuery()->awaitingPayment()->count()),
            'overdue' => Tab::make(__('usage-billing::billing.invoice.tabs.overdue'))
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', InvoiceStatus::Overdue))
                ->badge(fn (): int => static::getResource()::getEloquentQuery()
                    ->where('status', InvoiceStatus::Overdue)
                    ->count())
                ->badgeColor('danger'),
            'paid' => Tab::make(__('usage-billing::billing.invoice.tabs.paid'))
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', InvoiceStatus::Paid)),
            'all' => Tab::make(__('usage-billing::billing.invoice.tabs.all')),
        ];
    }

    public function getDefaultActiveTab(): string
    {
        return 'outstanding';
    }
}

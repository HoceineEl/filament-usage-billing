<?php

declare(strict_types=1);

namespace HoceineEl\UsageBilling\Filament\Resources\Subscriptions\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use HoceineEl\UsageBilling\Filament\Resources\Invoices\InvoiceResource;
use HoceineEl\UsageBilling\Models\Invoice;
use Illuminate\Database\Eloquent\Model;

class InvoicesRelationManager extends RelationManager
{
    protected static string $relationship = 'invoices';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('usage-billing::billing.invoice.plural');
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('period', 'desc')
            ->columns([
                TextColumn::make('number')
                    ->label(__('usage-billing::billing.invoice.fields.number'))
                    ->description(fn (Invoice $record): string => $record->period)
                    ->placeholder(__('usage-billing::billing.invoice.draft_placeholder'))
                    ->weight('medium'),
                TextColumn::make('total_ttc')
                    ->label(__('usage-billing::billing.invoice.fields.total_ttc'))
                    ->state(fn (Invoice $record): string => InvoiceResource::money($record->total_ttc, $record->currency))
                    ->description(fn (Invoice $record): ?string => $record->balanceDue() > 0
                        ? __('usage-billing::billing.invoice.balance_short', [
                            'amount' => InvoiceResource::money($record->balanceDue(), $record->currency),
                        ])
                        : null)
                    ->alignEnd(),
                TextColumn::make('status')
                    ->label(__('usage-billing::billing.invoice.fields.status'))
                    ->badge(),
                TextColumn::make('due_at')
                    ->label(__('usage-billing::billing.invoice.fields.due_at'))
                    ->date('d/m/Y')
                    ->placeholder('—'),
            ])
            ->recordUrl(fn (Invoice $record): string => InvoiceResource::getUrl('view', ['record' => $record]))
            ->emptyStateHeading(__('usage-billing::billing.invoice.empty.heading'));
    }
}

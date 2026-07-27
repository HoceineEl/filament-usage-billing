<?php

declare(strict_types=1);

namespace HoceineEl\UsageBilling\Filament\Resources\Invoices\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use HoceineEl\UsageBilling\Filament\Resources\Invoices\InvoiceResource;
use HoceineEl\UsageBilling\Filament\Resources\Payments\PaymentResource;
use HoceineEl\UsageBilling\Models\Payment;
use Illuminate\Database\Eloquent\Model;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('usage-billing::billing.payment.plural');
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('amount')
                    ->label(__('usage-billing::billing.payment.fields.amount'))
                    ->state(fn (Payment $record): string => InvoiceResource::money($record->amount, $record->currency))
                    ->description(fn (Payment $record): string => $record->method->getLabel())
                    ->weight('medium'),
                TextColumn::make('status')
                    ->label(__('usage-billing::billing.payment.fields.status'))
                    ->badge()
                    ->description(fn (Payment $record): ?string => $record->rejection_reason),
                TextColumn::make('paid_at')
                    ->label(__('usage-billing::billing.payment.fields.paid_at'))
                    ->date('d/m/Y')
                    ->placeholder('—'),
                TextColumn::make('reference')
                    ->label(__('usage-billing::billing.payment.fields.reference'))
                    ->placeholder('—')
                    ->color('gray'),
            ])
            ->recordActions([
                PaymentResource::validateAction(),
                PaymentResource::rejectAction(),
                PaymentResource::receiptAction(),
            ])
            ->emptyStateHeading(__('usage-billing::billing.payment.empty.heading'));
    }
}

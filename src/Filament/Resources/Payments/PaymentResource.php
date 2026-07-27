<?php

declare(strict_types=1);

namespace HoceineEl\UsageBilling\Filament\Resources\Payments;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use HoceineEl\UsageBilling\Enums\PaymentMethod;
use HoceineEl\UsageBilling\Enums\PaymentStatus;
use HoceineEl\UsageBilling\Filament\Concerns\BelongsToBillingNavigation;
use HoceineEl\UsageBilling\Filament\Resources\Invoices\InvoiceResource;
use HoceineEl\UsageBilling\Filament\Resources\Payments\Pages\ListPayments;
use HoceineEl\UsageBilling\Models\Payment;
use HoceineEl\UsageBilling\Services\SubscriptionManager;
use HoceineEl\UsageBilling\UsageBilling;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

/**
 * The daily queue: virements a cabinet declared, waiting for someone to check
 * them against the bank and say yes or no.
 */
class PaymentResource extends Resource
{
    use BelongsToBillingNavigation;

    protected static ?string $model = Payment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?int $navigationSort = 5;

    public static function getModelLabel(): string
    {
        return __('usage-billing::billing.payment.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('usage-billing::billing.payment.plural');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $count = UsageBilling::query('payment')->where('status', PaymentStatus::Pending)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('invoice'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('invoice.buyer_name')
                    ->label(__('usage-billing::billing.payment.fields.from'))
                    ->description(fn (Payment $record): string => $record->invoice?->number
                        ?? __('usage-billing::billing.invoice.draft_placeholder'))
                    ->searchable()
                    ->weight('medium'),
                TextColumn::make('amount')
                    ->label(__('usage-billing::billing.payment.fields.amount'))
                    ->state(fn (Payment $record): string => InvoiceResource::money($record->amount, $record->currency))
                    ->description(fn (Payment $record): string => $record->method->getLabel())
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('reference')
                    ->label(__('usage-billing::billing.payment.fields.reference'))
                    ->placeholder('—')
                    ->color('gray')
                    ->searchable(),
                TextColumn::make('status')
                    ->label(__('usage-billing::billing.payment.fields.status'))
                    ->badge()
                    ->description(fn (Payment $record): ?string => $record->rejection_reason)
                    ->sortable(),
                TextColumn::make('paid_at')
                    ->label(__('usage-billing::billing.payment.fields.paid_at'))
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('usage-billing::billing.payment.fields.status'))
                    ->options(PaymentStatus::class),
                SelectFilter::make('method')
                    ->label(__('usage-billing::billing.payment.fields.method'))
                    ->options(PaymentMethod::class),
            ])
            ->recordActions([
                static::receiptAction(),
                static::validateAction(),
                static::rejectAction(),
            ])
            ->recordUrl(fn (Payment $record): ?string => $record->invoice === null
                ? null
                : InvoiceResource::getUrl('view', ['record' => $record->invoice]))
            ->emptyStateHeading(__('usage-billing::billing.payment.empty.heading'))
            ->emptyStateDescription(__('usage-billing::billing.payment.empty.description'))
            ->emptyStateIcon(Heroicon::OutlinedBanknotes);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPayments::route('/'),
        ];
    }

    public static function validateAction(): Action
    {
        return Action::make('validate')
            ->label(__('usage-billing::billing.payment.actions.validate'))
            ->icon(Heroicon::CheckCircle)
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading(__('usage-billing::billing.payment.actions.validate'))
            ->modalDescription(fn (Payment $record): string => __('usage-billing::billing.payment.help.validate', [
                'amount' => InvoiceResource::money($record->amount, $record->currency),
                'buyer' => $record->invoice?->buyer_name ?? '',
            ]))
            ->visible(fn (Payment $record): bool => $record->isPending())
            ->action(function (Payment $record): void {
                app(SubscriptionManager::class)->validatePayment($record, auth()->user());

                Notification::make()
                    ->success()
                    ->title(__('usage-billing::billing.payment.notifications.validated'))
                    ->send();
            });
    }

    public static function rejectAction(): Action
    {
        return Action::make('reject')
            ->label(__('usage-billing::billing.payment.actions.reject'))
            ->icon(Heroicon::XCircle)
            ->color('danger')
            ->visible(fn (Payment $record): bool => $record->status !== PaymentStatus::Rejected)
            ->schema([
                Textarea::make('reason')
                    ->label(__('usage-billing::billing.payment.fields.rejection_reason'))
                    ->required()
                    ->rows(3)
                    ->helperText(__('usage-billing::billing.payment.help.reject')),
            ])
            ->action(function (Payment $record, array $data): void {
                app(SubscriptionManager::class)->rejectPayment($record, $data['reason'], auth()->user());

                Notification::make()
                    ->warning()
                    ->title(__('usage-billing::billing.payment.notifications.rejected'))
                    ->send();
            });
    }

    public static function receiptAction(): Action
    {
        return Action::make('receipt')
            ->label(__('usage-billing::billing.payment.actions.receipt'))
            ->icon(Heroicon::PaperClip)
            ->color('gray')
            ->visible(fn (Payment $record): bool => $record->receipt_path !== null)
            ->action(fn (Payment $record) => Storage::disk(
                (string) config('usage-billing.invoicing.receipts_disk', 'local')
            )->download($record->receipt_path));
    }
}

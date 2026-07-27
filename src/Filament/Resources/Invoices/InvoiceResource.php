<?php

declare(strict_types=1);

namespace HoceineEl\UsageBilling\Filament\Resources\Invoices;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use HoceineEl\UsageBilling\Contracts\InvoiceRenderer;
use HoceineEl\UsageBilling\Enums\InvoiceStatus;
use HoceineEl\UsageBilling\Enums\PaymentMethod;
use HoceineEl\UsageBilling\Filament\Concerns\BelongsToBillingNavigation;
use HoceineEl\UsageBilling\Filament\Resources\Invoices\Pages\ListInvoices;
use HoceineEl\UsageBilling\Filament\Resources\Invoices\Pages\ViewInvoice;
use HoceineEl\UsageBilling\Models\Invoice;
use HoceineEl\UsageBilling\Services\SubscriptionManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

class InvoiceResource extends Resource
{
    use BelongsToBillingNavigation;

    protected static ?string $model = Invoice::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?int $navigationSort = 4;

    public static function getModelLabel(): string
    {
        return __('usage-billing::billing.invoice.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('usage-billing::billing.invoice.plural');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function money(float|string|null $amount, string $currency = 'MAD'): string
    {
        return number_format((float) $amount, 2, ',', ' ').' '.$currency;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columns(4)
                ->schema([
                    TextEntry::make('number')
                        ->label(__('usage-billing::billing.invoice.fields.number'))
                        ->placeholder(__('usage-billing::billing.invoice.draft_placeholder'))
                        ->weight('bold')
                        ->size('lg'),
                    TextEntry::make('status')
                        ->label(__('usage-billing::billing.invoice.fields.status'))
                        ->badge(),
                    TextEntry::make('issued_at')
                        ->label(__('usage-billing::billing.invoice.fields.issued_at'))
                        ->date('d/m/Y')
                        ->placeholder('—'),
                    TextEntry::make('due_at')
                        ->label(__('usage-billing::billing.invoice.fields.due_at'))
                        ->date('d/m/Y')
                        ->placeholder('—')
                        ->color(fn (Invoice $record): string => $record->status === InvoiceStatus::Overdue ? 'danger' : 'gray'),
                ]),

            Grid::make(2)->schema([
                Section::make(__('usage-billing::billing.invoice.sections.buyer'))
                    ->schema([
                        TextEntry::make('buyer_name')
                            ->hiddenLabel()
                            ->weight('medium'),
                        TextEntry::make('buyer_ice')
                            ->label(__('usage-billing::billing.invoice.fields.ice'))
                            ->placeholder('—'),
                        TextEntry::make('buyer_identifiant_fiscal')
                            ->label(__('usage-billing::billing.invoice.fields.identifiant_fiscal'))
                            ->placeholder('—'),
                        TextEntry::make('buyer_address')
                            ->label(__('usage-billing::billing.invoice.fields.address'))
                            ->placeholder('—'),
                    ]),

                Section::make(__('usage-billing::billing.invoice.sections.totals'))
                    ->schema([
                        TextEntry::make('subtotal_ht')
                            ->label(__('usage-billing::billing.invoice.fields.subtotal_ht'))
                            ->state(fn (Invoice $record): string => static::money($record->subtotal_ht, $record->currency)),
                        TextEntry::make('tva_amount')
                            ->label(fn (Invoice $record): string => __('usage-billing::billing.invoice.fields.tva', [
                                'rate' => rtrim(rtrim((string) $record->tva_rate, '0'), '.'),
                            ]))
                            ->state(fn (Invoice $record): string => static::money($record->tva_amount, $record->currency)),
                        TextEntry::make('total_ttc')
                            ->label(__('usage-billing::billing.invoice.fields.total_ttc'))
                            ->state(fn (Invoice $record): string => static::money($record->total_ttc, $record->currency))
                            ->weight('bold')
                            ->size('lg'),
                        TextEntry::make('balance')
                            ->label(__('usage-billing::billing.invoice.fields.balance_due'))
                            ->state(fn (Invoice $record): string => static::money($record->balanceDue(), $record->currency))
                            ->color(fn (Invoice $record): string => $record->balanceDue() > 0 ? 'danger' : 'success'),
                    ]),
            ]),

            Section::make(__('usage-billing::billing.invoice.sections.lines'))
                ->schema([
                    RepeatableEntry::make('lines')
                        ->hiddenLabel()
                        ->contained(false)
                        ->schema([
                            Grid::make(4)->schema([
                                TextEntry::make('description')
                                    ->hiddenLabel()
                                    ->columnSpan(2),
                                TextEntry::make('quantity')
                                    ->label(__('usage-billing::billing.invoice.fields.quantity'))
                                    ->state(fn ($record): string => rtrim(rtrim(number_format((float) $record->quantity, 2, ',', ' '), '0'), ',')),
                                TextEntry::make('amount_ht')
                                    ->label(__('usage-billing::billing.invoice.fields.amount_ht'))
                                    ->state(fn ($record): string => static::money($record->amount_ht))
                                    ->alignEnd(),
                            ]),
                        ]),
                ]),

            Section::make(__('usage-billing::billing.invoice.sections.usage'))
                ->description(__('usage-billing::billing.invoice.help.usage'))
                ->collapsible()
                ->collapsed()
                ->schema([
                    TextEntry::make('usage_snapshot')
                        ->hiddenLabel()
                        ->state(fn (Invoice $record): string => static::usageSummary($record))
                        ->markdown(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['subscriber', 'subscription.plan']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('number')
                    ->label(__('usage-billing::billing.invoice.fields.number'))
                    ->description(fn (Invoice $record): string => $record->buyer_name)
                    ->placeholder(__('usage-billing::billing.invoice.draft_placeholder'))
                    ->searchable(['number', 'buyer_name'])
                    ->weight('medium'),
                TextColumn::make('period')
                    ->label(__('usage-billing::billing.invoice.fields.period'))
                    ->badge()
                    ->color('gray')
                    ->sortable(),
                TextColumn::make('total_ttc')
                    ->label(__('usage-billing::billing.invoice.fields.total_ttc'))
                    ->state(fn (Invoice $record): string => static::money($record->total_ttc, $record->currency))
                    ->description(fn (Invoice $record): ?string => $record->balanceDue() > 0 && $record->amount_paid > 0
                        ? __('usage-billing::billing.invoice.balance_short', [
                            'amount' => static::money($record->balanceDue(), $record->currency),
                        ])
                        : null)
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('usage-billing::billing.invoice.fields.status'))
                    ->badge()
                    ->sortable(),
                TextColumn::make('due_at')
                    ->label(__('usage-billing::billing.invoice.fields.due_at'))
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('usage-billing::billing.invoice.fields.status'))
                    ->options(InvoiceStatus::class),
                SelectFilter::make('period')
                    ->label(__('usage-billing::billing.invoice.fields.period'))
                    ->options(fn (): array => Invoice::query()
                        ->distinct()
                        ->orderByDesc('period')
                        ->pluck('period', 'period')
                        ->all()),
            ])
            ->recordActions([
                ViewAction::make(),
                static::recordPaymentAction(),
                static::downloadAction(),
            ])
            ->emptyStateHeading(__('usage-billing::billing.invoice.empty.heading'))
            ->emptyStateDescription(__('usage-billing::billing.invoice.empty.description'));
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\PaymentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInvoices::route('/'),
            'view' => ViewInvoice::route('/{record}'),
        ];
    }

    public static function recordPaymentAction(): Action
    {
        return Action::make('recordPayment')
            ->label(__('usage-billing::billing.invoice.actions.record_payment'))
            ->icon(Heroicon::Banknotes)
            ->color('success')
            ->visible(fn (Invoice $record): bool => $record->status->awaitsPayment())
            ->schema(fn (Invoice $record): array => [
                Grid::make(2)->schema([
                    TextInput::make('amount')
                        ->label(__('usage-billing::billing.payment.fields.amount'))
                        ->numeric()
                        ->required()
                        ->default($record->balanceDue())
                        ->suffix($record->currency),
                    Select::make('method')
                        ->label(__('usage-billing::billing.payment.fields.method'))
                        ->options(PaymentMethod::class)
                        ->default(PaymentMethod::Virement->value)
                        ->required()
                        ->native(false),
                    DatePicker::make('paid_at')
                        ->label(__('usage-billing::billing.payment.fields.paid_at'))
                        ->default(now())
                        ->required(),
                    TextInput::make('reference')
                        ->label(__('usage-billing::billing.payment.fields.reference'))
                        ->helperText(__('usage-billing::billing.payment.help.reference')),
                ]),
                Textarea::make('notes')
                    ->label(__('usage-billing::billing.payment.fields.notes'))
                    ->rows(2),
            ])
            ->action(function (Invoice $record, array $data): void {
                $manager = app(SubscriptionManager::class);

                // Recorded by an operator who has already seen the money, so it
                // is validated in the same step rather than queued for review.
                $manager->validatePayment(
                    $manager->declarePayment($record, $data, auth()->user()),
                    auth()->user(),
                );

                Notification::make()
                    ->success()
                    ->title(__('usage-billing::billing.payment.notifications.recorded'))
                    ->send();
            });
    }

    private static function downloadAction(): Action
    {
        return Action::make('download')
            ->label(__('usage-billing::billing.invoice.actions.download'))
            ->icon(Heroicon::ArrowDownTray)
            ->color('gray')
            ->visible(fn (Invoice $record): bool => $record->status !== InvoiceStatus::Draft)
            ->action(function (Invoice $record) {
                $disk = Storage::disk((string) config('usage-billing.invoicing.pdf_disk', 'local'));

                if ($record->pdf_path === null || ! $disk->exists($record->pdf_path)) {
                    $record->forceFill(['pdf_path' => app(InvoiceRenderer::class)->render($record)])->save();
                }

                return $disk->download($record->pdf_path, "{$record->number}.pdf");
            });
    }

    private static function usageSummary(Invoice $record): string
    {
        $snapshot = collect($record->usage_snapshot ?? [])
            ->filter(fn (array $module): bool => (int) ($module['total'] ?? 0) > 0);

        if ($snapshot->isEmpty()) {
            return __('usage-billing::billing.invoice.no_usage');
        }

        return $snapshot
            ->map(function (array $module): string {
                $heading = "**{$module['module_label']}** — ".
                    number_format((float) $module['total'], 0, ',', ' ').' '.$module['unit_label'].
                    ($module['allowance'] === null
                        ? ''
                        : ' / '.number_format((float) $module['allowance'], 0, ',', ' '));

                $lines = collect($module['buckets'] ?? [])
                    ->sortByDesc('quantity')
                    ->map(fn (array $bucket): string => '- '.
                        ($bucket['attribution_label'] ?? __('usage-billing::billing.invoice.unattributed')).
                        ': '.number_format((float) $bucket['quantity'], 0, ',', ' '))
                    ->implode("\n");

                return $heading."\n\n".$lines;
            })
            ->implode("\n\n");
    }
}

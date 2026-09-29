<?php

declare(strict_types=1);

namespace HoceineEl\UsageBilling\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use HoceineEl\UsageBilling\Contracts\InvoiceRenderer;
use HoceineEl\UsageBilling\Contracts\Subscribable;
use HoceineEl\UsageBilling\Data\ModuleUsageSummary;
use HoceineEl\UsageBilling\Data\UsageBucket;
use HoceineEl\UsageBilling\Enums\InvoiceStatus;
use HoceineEl\UsageBilling\Enums\PaymentMethod;
use HoceineEl\UsageBilling\Enums\ResetPeriod;
use HoceineEl\UsageBilling\Filament\UsageBillingPlugin;
use HoceineEl\UsageBilling\Http\Middleware\EnsureBillingActive;
use HoceineEl\UsageBilling\Models\Invoice;
use HoceineEl\UsageBilling\Models\Payment;
use HoceineEl\UsageBilling\Models\Subscription;
use HoceineEl\UsageBilling\Services\PaymentGatewayManager;
use HoceineEl\UsageBilling\Services\SubscriptionManager;
use HoceineEl\UsageBilling\Support\Period;
use HoceineEl\UsageBilling\UsageBilling;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * What the subscriber sees: where its consumption stands against the plan it
 * paid for, when the term runs out, and the factures it has to settle.
 *
 * @property-read Table $table
 */
class TenantBilling extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'usage-billing::filament.pages.tenant-billing';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static ?string $slug = 'billing';

    protected static ?int $navigationSort = 90;

    /** @var Collection<string, ModuleUsageSummary>|null */
    private ?Collection $summaries = null;

    public static function getNavigationLabel(): string
    {
        return __('usage-billing::billing.tenant.nav');
    }

    public function getTitle(): string|Htmlable
    {
        return __('usage-billing::billing.tenant.title');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('usage-billing::billing.tenant.subheading', ['period' => Period::current()->label()]);
    }

    public static function canAccess(): bool
    {
        $plugin = UsageBillingPlugin::get();

        return $plugin->resolveSubscriber() instanceof Subscribable && $plugin->canAccessBilling();
    }

    public function getSubscriber(): Model&Subscribable
    {
        $subscriber = UsageBillingPlugin::get()->resolveSubscriber();

        abort_unless($subscriber instanceof Subscribable, 404);

        return $subscriber;
    }

    public function getSubscription(): ?Subscription
    {
        return $this->getSubscriber()->currentSubscription();
    }

    public function getLockReason(): ?string
    {
        if (! $this->getSubscriber()->usageLimitsEnforced()) {
            return null;
        }

        $subscription = $this->getSubscription();

        return $subscription?->isUsable() === true
            ? null
            : EnsureBillingActive::lockMessage($subscription?->status);
    }

    public function getSupportEmail(): ?string
    {
        $email = config('usage-billing.seller.email');

        return is_string($email) && $email !== '' ? $email : null;
    }

    /**
     * Bank details the subscriber pays into, printed next to the facture that
     * asks for the money.
     *
     * @return array{beneficiary: string, bank: ?string, rib: string}|null
     */
    public function getBankDetails(): ?array
    {
        $rib = config('usage-billing.seller.rib');

        if (! is_string($rib) || $rib === '') {
            return null;
        }

        return [
            'beneficiary' => (string) (config('usage-billing.seller.name') ?? config('app.name')),
            'bank' => config('usage-billing.seller.bank_name'),
            'rib' => $rib,
        ];
    }

    /**
     * Recomputed from the source tables, so it is exact and worth holding on
     * to for the rest of the render.
     *
     * @return Collection<string, ModuleUsageSummary>
     */
    public function getUsageSummaries(): Collection
    {
        return $this->summaries ??= $this->getSubscriber()->usageSummary();
    }

    /**
     * One row per module, measured against the window its allowance covers:
     * this month, or today for a module that resets daily.
     *
     * @return Collection<string, array{label: string, unit: string, used: int, allowance: ?int, percent: ?int, over: int, near_limit: bool, reset_period: ResetPeriod, month_total: int}>
     */
    public function getUsageRows(): Collection
    {
        $subscriber = $this->getSubscriber();

        return $this->getUsageSummaries()->map(function (ModuleUsageSummary $summary) use ($subscriber): array {
            $used = $summary->resetPeriod === ResetPeriod::Month
                ? $summary->total
                : $subscriber->usageFor($summary->moduleKey);
            $percent = $summary->allowance === null || $summary->allowance === 0
                ? null
                : min(100, (int) round($used / $summary->allowance * 100));

            return [
                'label' => $summary->moduleLabel,
                'unit' => $summary->unitLabel,
                'used' => $used,
                'allowance' => $summary->allowance,
                'percent' => $percent,
                'over' => $summary->allowance === null ? 0 : max(0, $used - $summary->allowance),
                'near_limit' => $percent !== null && $percent >= 80,
                'reset_period' => $summary->resetPeriod,
                'month_total' => $summary->total,
            ];
        });
    }

    /**
     * Consumption rolled up per attribution across every module, so the
     * subscriber can see what drives its usage.
     *
     * @return Collection<int, array{label: string, total: int, modules: array<string, int>}>
     */
    public function getBreakdown(): Collection
    {
        return $this->getUsageSummaries()
            ->flatMap(fn (ModuleUsageSummary $summary): array => $summary->buckets
                ->filter(fn (UsageBucket $bucket): bool => $bucket->isAttributed())
                ->map(fn (UsageBucket $bucket): array => [
                    'key' => $bucket->key(),
                    'label' => $bucket->attributionLabel ?? '—',
                    'module' => $summary->moduleLabel,
                    'quantity' => $bucket->quantity,
                ])
                ->all())
            ->groupBy('key')
            ->map(fn (Collection $rows): array => [
                'label' => (string) $rows->first()['label'],
                'total' => (int) $rows->sum('quantity'),
                'modules' => $rows->mapWithKeys(fn (array $row): array => [(string) $row['module'] => (int) $row['quantity']])->all(),
            ])
            ->sortByDesc('total')
            ->values();
    }

    public function getOutstandingInvoice(): ?Invoice
    {
        return $this->getSubscription()
            ?->invoices()
            ->awaitingPayment()
            ->oldest('due_at')
            ->first();
    }

    public function getDaysUntilRenewal(): ?int
    {
        $days = $this->getSubscription()?->daysUntilTermEnds();

        return $days === null ? null : max(0, $days);
    }

    public function isRenewalApproaching(): bool
    {
        $days = $this->getDaysUntilRenewal();
        $notice = (int) ($this->getSubscription()?->plan?->renewal_notice_days ?? 30);

        return $days !== null && $days <= $notice;
    }

    public function money(float|string|null $amount, ?string $currency = null): string
    {
        $currency ??= $this->getSubscription()?->plan?->currency ?? UsageBilling::currency();

        return number_format((float) $amount, 2, ',', ' ').' '.$currency;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(function (): Builder {
                $subscriber = $this->getSubscriber();

                return UsageBilling::query('invoice')
                    ->where('subscriber_type', $subscriber->getMorphClass())
                    ->where('subscriber_id', $subscriber->getKey())
                    ->where('status', '!=', InvoiceStatus::Draft);
            })
            ->heading(__('usage-billing::billing.tenant.invoices.heading'))
            ->defaultSort('issued_at', 'desc')
            ->paginated([5, 10, 25])
            ->columns([
                TextColumn::make('number')
                    ->label(__('usage-billing::billing.invoice.fields.number'))
                    ->description(fn (Invoice $record): ?string => $record->issued_at?->translatedFormat('d/m/Y'))
                    ->weight('medium'),
                TextColumn::make('total_ttc')
                    ->label(__('usage-billing::billing.invoice.fields.total_ttc'))
                    ->state(fn (Invoice $record): string => $this->money($record->total_ttc, $record->currency))
                    ->description(fn (Invoice $record): ?string => $record->status->awaitsPayment() && $record->balanceDue() > 0
                        ? __('usage-billing::billing.invoice.balance_short', ['amount' => $this->money($record->balanceDue(), $record->currency)])
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
            ->recordActions([
                $this->payOnlineAction(),
                $this->declarePaymentAction(),
                $this->downloadInvoiceAction(),
                $this->downloadReceiptAction(),
            ])
            ->emptyStateHeading(__('usage-billing::billing.tenant.invoices.empty_heading'))
            ->emptyStateDescription(__('usage-billing::billing.tenant.invoices.empty_description'))
            ->emptyStateIcon(Heroicon::OutlinedDocumentText);
    }

    public function payOnlineAction(): Action
    {
        return Action::make('payOnline')
            ->label(__('usage-billing::billing.tenant.actions.pay_online'))
            ->icon(Heroicon::CreditCard)
            ->color('primary')
            ->authorize(fn (): bool => static::canAccess())
            ->visible(fn (Invoice $record): bool => $record->status->awaitsPayment() && app(PaymentGatewayManager::class)->enabled())
            ->action(fn (Invoice $record) => redirect()->away(app(PaymentGatewayManager::class)->driver()->checkout($record)));
    }

    public function declarePaymentAction(): Action
    {
        return Action::make('declarePayment')
            ->label(__('usage-billing::billing.tenant.actions.declare_payment'))
            ->icon(Heroicon::Banknotes)
            ->color('gray')
            ->authorize(fn (): bool => static::canAccess())
            ->visible(fn (Invoice $record): bool => $record->status->awaitsPayment())
            ->modalHeading(__('usage-billing::billing.tenant.actions.declare_payment'))
            ->modalDescription(__('usage-billing::billing.tenant.help.declare_payment'))
            ->modalSubmitActionLabel(__('usage-billing::billing.tenant.actions.send_declaration'))
            ->schema(fn (Invoice $record): array => [
                Grid::make(2)->schema([
                    TextInput::make('amount')
                        ->label(__('usage-billing::billing.payment.fields.amount'))
                        ->numeric()
                        ->required()
                        ->minValue(0.01)
                        ->maxValue($record->balanceDue())
                        ->default($record->balanceDue())
                        ->suffix($record->currency),
                    Select::make('method')
                        ->label(__('usage-billing::billing.payment.fields.method'))
                        ->options(collect(PaymentMethod::cases())
                            ->filter(fn (PaymentMethod $method): bool => $method->requiresManualValidation())
                            ->mapWithKeys(fn (PaymentMethod $method): array => [$method->value => $method->getLabel()])
                            ->all())
                        ->default(PaymentMethod::Virement->value)
                        ->required()
                        ->native(false),
                    DatePicker::make('paid_at')
                        ->label(__('usage-billing::billing.payment.fields.paid_at'))
                        ->default(now())
                        ->maxDate(now())
                        ->required(),
                    TextInput::make('reference')
                        ->label(__('usage-billing::billing.payment.fields.reference'))
                        ->helperText(__('usage-billing::billing.tenant.help.reference')),
                ]),
                FileUpload::make('receipt_path')
                    ->label(__('usage-billing::billing.tenant.fields.receipt'))
                    ->disk((string) config('usage-billing.invoicing.receipts_disk', 'local'))
                    ->directory((string) config('usage-billing.invoicing.receipts_directory', 'usage-billing/receipts'))
                    ->visibility('private')
                    ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                    ->maxSize(5120)
                    ->helperText(__('usage-billing::billing.tenant.help.receipt')),
            ])
            ->action(function (Invoice $record, array $data): void {
                app(SubscriptionManager::class)->declarePayment($record, $data, auth()->user());

                Notification::make()
                    ->success()
                    ->title(__('usage-billing::billing.tenant.notifications.payment_declared'))
                    ->body(__('usage-billing::billing.tenant.notifications.payment_declared_body'))
                    ->send();
            });
    }

    public function downloadInvoiceAction(): Action
    {
        return Action::make('downloadInvoice')
            ->label(__('usage-billing::billing.invoice.actions.download'))
            ->icon(Heroicon::ArrowDownTray)
            ->color('gray')
            ->authorize(fn (): bool => static::canAccess())
            ->action(function (Invoice $record) {
                $disk = Storage::disk((string) config('usage-billing.invoicing.pdf_disk', 'local'));

                if ($record->pdf_path === null || ! $disk->exists($record->pdf_path)) {
                    $record->forceFill(['pdf_path' => app(InvoiceRenderer::class)->render($record)])->save();
                }

                return $disk->download($record->pdf_path, $record->number.'.'.pathinfo((string) $record->pdf_path, PATHINFO_EXTENSION));
            });
    }

    /**
     * The receipt the subscriber attached to its latest declaration. Resolved
     * through the invoice, which the table has already scoped to this
     * subscriber, so no other account's file is reachable.
     */
    public function downloadReceiptAction(): Action
    {
        return Action::make('downloadReceipt')
            ->label(__('usage-billing::billing.payment.actions.receipt'))
            ->icon(Heroicon::PaperClip)
            ->color('gray')
            ->authorize(fn (): bool => static::canAccess())
            ->visible(fn (Invoice $record): bool => $this->latestReceipt($record) !== null)
            ->action(fn (Invoice $record) => Storage::disk((string) config('usage-billing.invoicing.receipts_disk', 'local'))
                ->download((string) $this->latestReceipt($record)?->receipt_path));
    }

    private function latestReceipt(Invoice $invoice): ?Payment
    {
        /** @var Payment|null $payment */
        $payment = $invoice->payments()->whereNotNull('receipt_path')->latest()->first();

        return $payment;
    }
}

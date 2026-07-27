<?php

declare(strict_types=1);

namespace HoceineEl\UsageBilling\Filament\Resources\Subscriptions;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use HoceineEl\UsageBilling\Enums\SubscriptionStatus;
use HoceineEl\UsageBilling\Filament\Concerns\BelongsToBillingNavigation;
use HoceineEl\UsageBilling\Filament\Resources\Subscriptions\Pages\ListSubscriptions;
use HoceineEl\UsageBilling\Filament\Resources\Subscriptions\Pages\ViewSubscription;
use HoceineEl\UsageBilling\Models\Plan;
use HoceineEl\UsageBilling\Models\Subscription;
use HoceineEl\UsageBilling\Services\SubscriptionManager;
use HoceineEl\UsageBilling\Services\TermBiller;
use HoceineEl\UsageBilling\UsageBilling;
use Illuminate\Database\Eloquent\Builder;

class SubscriptionResource extends Resource
{
    use BelongsToBillingNavigation;

    protected static ?string $model = Subscription::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?int $navigationSort = 3;

    public static function getModelLabel(): string
    {
        return __('usage-billing::billing.subscription.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('usage-billing::billing.subscription.plural');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('usage-billing::billing.subscription.sections.overview'))
                ->columns(4)
                ->schema([
                    TextEntry::make('subscriber')
                        ->label(__('usage-billing::billing.subscription.fields.subscriber'))
                        ->state(fn (Subscription $record): string => static::subscriberName($record))
                        ->weight('medium'),
                    TextEntry::make('plan')
                        ->label(__('usage-billing::billing.subscription.fields.plan'))
                        ->state(fn (Subscription $record): string => $record->plan?->displayName() ?? '—'),
                    TextEntry::make('status')
                        ->label(__('usage-billing::billing.subscription.fields.status'))
                        ->badge(),
                    TextEntry::make('starts_at')
                        ->label(__('usage-billing::billing.subscription.fields.starts_at'))
                        ->date('d/m/Y'),
                    TextEntry::make('trial_ends_at')
                        ->label(__('usage-billing::billing.subscription.fields.trial_ends_at'))
                        ->date('d/m/Y')
                        ->placeholder('—'),
                    TextEntry::make('grace_ends_at')
                        ->label(__('usage-billing::billing.subscription.fields.grace_ends_at'))
                        ->date('d/m/Y')
                        ->placeholder('—')
                        ->color(fn (Subscription $record): string => $record->inGracePeriod() ? 'warning' : 'gray'),
                    TextEntry::make('cancelled_at')
                        ->label(__('usage-billing::billing.subscription.fields.cancelled_at'))
                        ->date('d/m/Y')
                        ->placeholder('—'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['plan', 'subscriber']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('subscriber')
                    ->label(__('usage-billing::billing.subscription.fields.subscriber'))
                    ->state(fn (Subscription $record): string => static::subscriberName($record))
                    ->description(fn (Subscription $record): string => $record->plan?->displayName() ?? '—')
                    ->weight('medium'),
                TextColumn::make('status')
                    ->label(__('usage-billing::billing.subscription.fields.status'))
                    ->badge()
                    ->description(fn (Subscription $record): ?string => $record->inGracePeriod()
                        ? __('usage-billing::billing.subscription.grace_until', [
                            'date' => $record->grace_ends_at?->isoFormat('D MMM'),
                        ])
                        : null)
                    ->sortable(),
                TextColumn::make('starts_at')
                    ->label(__('usage-billing::billing.subscription.fields.starts_at'))
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('invoices_count')
                    ->label(__('usage-billing::billing.subscription.fields.invoices'))
                    ->counts('invoices')
                    ->badge()
                    ->color('gray')
                    ->alignCenter(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('usage-billing::billing.subscription.fields.status'))
                    ->options(SubscriptionStatus::class),
                SelectFilter::make('plan_id')
                    ->label(__('usage-billing::billing.subscription.fields.plan'))
                    ->options(fn (): array => static::planOptions(activeOnly: false)),
            ])
            ->recordActions([
                ViewAction::make(),
                static::issueTermAction(),
                static::upgradeAction(),
                static::changePlanAction(),
                static::cancelAction(),
            ])
            ->emptyStateHeading(__('usage-billing::billing.subscription.empty.heading'))
            ->emptyStateDescription(__('usage-billing::billing.subscription.empty.description'));
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\InvoicesRelationManager::class,
            RelationManagers\EventsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSubscriptions::route('/'),
            'view' => ViewSubscription::route('/{record}'),
        ];
    }

    public static function subscriberName(Subscription $record): string
    {
        $subscriber = $record->subscriber;

        if ($subscriber === null) {
            return __('usage-billing::billing.subscription.deleted_subscriber');
        }

        $name = $subscriber->getAttribute('name');

        return is_string($name) && $name !== ''
            ? $name
            : class_basename($subscriber).' #'.$subscriber->getKey();
    }

    /**
     * Sells a term to a subscriber that has none. The subscription is created
     * awaiting payment and its first facture goes out at once — nothing opens
     * until that facture is settled.
     */
    public static function startSubscriptionAction(): Action
    {
        return Action::make('startSubscription')
            ->label(__('usage-billing::billing.subscription.actions.start'))
            ->icon(Heroicon::PlusCircle)
            ->modalDescription(__('usage-billing::billing.subscription.help.start'))
            ->schema([
                Select::make('subscriber_id')
                    ->label(__('usage-billing::billing.subscription.fields.subscriber'))
                    ->options(fn (): array => UsageBilling::subscriberOptions())
                    ->searchable()
                    ->required()
                    ->native(false),
                Select::make('plan_id')
                    ->label(__('usage-billing::billing.subscription.fields.plan'))
                    ->options(fn (): array => static::planOptions())
                    ->required()
                    ->native(false),
                Toggle::make('with_trial')
                    ->label(__('usage-billing::billing.subscription.fields.with_trial'))
                    ->helperText(__('usage-billing::billing.subscription.help.with_trial')),
            ])
            ->action(function (array $data): void {
                $subscriberModel = UsageBilling::subscriberModel();

                if ($subscriberModel === null) {
                    return;
                }

                $subscriber = $subscriberModel::query()->findOrFail($data['subscriber_id']);

                if (UsageBilling::query('subscription')->forSubscriber($subscriber)->usable()->exists()) {
                    Notification::make()
                        ->warning()
                        ->title(__('usage-billing::billing.subscription.notifications.already_subscribed'))
                        ->send();

                    return;
                }

                /** @var Plan $plan */
                $plan = UsageBilling::query('plan')->findOrFail($data['plan_id']);

                $subscription = app(SubscriptionManager::class)
                    ->start($subscriber, $plan, (bool) ($data['with_trial'] ?? false));

                $invoice = app(TermBiller::class)->issueTermInvoice($subscription);

                Notification::make()
                    ->success()
                    ->title(__('usage-billing::billing.subscription.notifications.started'))
                    ->body(__('usage-billing::billing.subscription.notifications.term_invoiced', [
                        'number' => $invoice?->number ?? '—',
                    ]))
                    ->send();
            });
    }

    /**
     * Re-issues the term facture for a subscription that is waiting on one —
     * a first purchase whose facture was never raised, or a lapsed term.
     */
    private static function issueTermAction(): Action
    {
        return Action::make('issueTerm')
            ->label(__('usage-billing::billing.subscription.actions.issue_term'))
            ->icon(Heroicon::DocumentPlus)
            ->color('primary')
            ->requiresConfirmation()
            ->modalDescription(__('usage-billing::billing.subscription.help.issue_term'))
            ->visible(fn (Subscription $record): bool => $record->awaitsFirstPayment() || $record->hasTermExpired())
            ->action(function (Subscription $record): void {
                $invoice = app(TermBiller::class)->issueTermInvoice($record);

                Notification::make()
                    ->success()
                    ->title(__('usage-billing::billing.subscription.notifications.term_invoiced', [
                        'number' => $invoice?->number ?? '—',
                    ]))
                    ->send();
            });
    }

    /**
     * Moving up mid-term is sold, not granted: the difference is invoiced for
     * the months that remain and the new plan applies once it is paid.
     */
    private static function upgradeAction(): Action
    {
        return Action::make('upgrade')
            ->label(__('usage-billing::billing.subscription.actions.upgrade'))
            ->icon(Heroicon::ArrowTrendingUp)
            ->color('gray')
            ->visible(fn (Subscription $record): bool => $record->isUsable())
            ->modalDescription(__('usage-billing::billing.subscription.help.upgrade'))
            ->schema([
                Select::make('plan_id')
                    ->label(__('usage-billing::billing.subscription.fields.plan'))
                    ->options(fn (): array => static::planOptions())
                    ->required()
                    ->native(false),
            ])
            ->action(function (Subscription $record, array $data): void {
                /** @var Plan $plan */
                $plan = UsageBilling::query('plan')->findOrFail($data['plan_id']);

                $invoice = app(TermBiller::class)->issueUpgradeInvoice($record, $plan);

                if ($invoice === null) {
                    Notification::make()
                        ->warning()
                        ->title(__('usage-billing::billing.subscription.notifications.no_upgrade'))
                        ->send();

                    return;
                }

                Notification::make()
                    ->success()
                    ->title(__('usage-billing::billing.subscription.notifications.upgrade_invoiced', [
                        'number' => $invoice->number,
                    ]))
                    ->send();
            });
    }

    /**
     * Retired plans stay selectable for filtering, so a subscription still
     * sitting on one can be found; they must never be sellable.
     *
     * @return array<int|string, string>
     */
    private static function planOptions(bool $activeOnly = true): array
    {
        return UsageBilling::query('plan')
            ->when($activeOnly, fn (Builder $query): Builder => $query->active())
            ->ordered()
            ->get()
            ->mapWithKeys(fn (Plan $plan): array => [$plan->getKey() => $plan->displayName()])
            ->all();
    }

    private static function changePlanAction(): Action
    {
        return Action::make('changePlan')
            ->label(__('usage-billing::billing.subscription.actions.change_plan'))
            ->icon(Heroicon::ArrowsRightLeft)
            ->color('gray')
            ->schema([
                Select::make('plan_id')
                    ->label(__('usage-billing::billing.subscription.fields.plan'))
                    ->options(fn (): array => static::planOptions())
                    ->required()
                    ->native(false),
            ])
            ->modalDescription(__('usage-billing::billing.subscription.help.change_plan'))
            ->action(function (Subscription $record, array $data): void {
                /** @var Plan $plan */
                $plan = UsageBilling::query('plan')->findOrFail($data['plan_id']);

                app(SubscriptionManager::class)->changePlan($record, $plan, auth()->user());

                Notification::make()
                    ->success()
                    ->title(__('usage-billing::billing.subscription.notifications.plan_changed'))
                    ->send();
            });
    }

    private static function cancelAction(): Action
    {
        return Action::make('cancel')
            ->label(__('usage-billing::billing.subscription.actions.cancel'))
            ->icon(Heroicon::XCircle)
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription(__('usage-billing::billing.subscription.help.cancel'))
            ->visible(fn (Subscription $record): bool => $record->status !== SubscriptionStatus::Cancelled)
            ->action(function (Subscription $record): void {
                app(SubscriptionManager::class)->cancel($record, auth()->user());

                Notification::make()
                    ->success()
                    ->title(__('usage-billing::billing.subscription.notifications.cancelled'))
                    ->send();
            });
    }
}

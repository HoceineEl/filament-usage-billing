<?php

declare(strict_types=1);

namespace HoceineEl\UsageBilling\Filament\Resources\Plans;

use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use HoceineEl\UsageBilling\Filament\Concerns\BelongsToBillingNavigation;
use HoceineEl\UsageBilling\Filament\Resources\Plans\Pages\CreatePlan;
use HoceineEl\UsageBilling\Filament\Resources\Plans\Pages\EditPlan;
use HoceineEl\UsageBilling\Filament\Resources\Plans\Pages\ListPlans;
use HoceineEl\UsageBilling\Models\Plan;
use HoceineEl\UsageBilling\Models\PlanModule;
use HoceineEl\UsageBilling\UsageBilling;
use Illuminate\Database\Eloquent\Builder;

class PlanResource extends Resource
{
    use BelongsToBillingNavigation;

    protected static ?string $model = Plan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static ?int $navigationSort = 1;

    public static function getModelLabel(): string
    {
        return __('usage-billing::billing.plan.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('usage-billing::billing.plan.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('usage-billing::billing.plan.sections.identity'))
                ->columns(2)
                ->schema([
                    TextInput::make('slug')
                        ->label(__('usage-billing::billing.plan.fields.slug'))
                        ->required()
                        ->alphaDash()
                        ->unique(ignoreRecord: true)
                        ->helperText(__('usage-billing::billing.plan.help.slug')),
                    TextInput::make('sort_order')
                        ->label(__('usage-billing::billing.plan.fields.sort_order'))
                        ->numeric()
                        ->default(0),
                    Grid::make(count(static::locales()))
                        ->columnSpanFull()
                        ->schema(collect(static::locales())
                            ->map(fn (string $label, string $locale): TextInput => TextInput::make("name.{$locale}")
                                ->label(__('usage-billing::billing.plan.fields.name').' — '.$label)
                                ->required($locale === array_key_first(static::locales())))
                            ->values()
                            ->all()),
                ]),

            Section::make(__('usage-billing::billing.plan.sections.pricing'))
                ->columns(3)
                ->schema([
                    TextInput::make('price_ht')
                        ->label(__('usage-billing::billing.plan.fields.price_ht'))
                        ->numeric()
                        ->required()
                        ->default(0)
                        ->suffix(fn (): string => UsageBilling::currency()),
                    TextInput::make('tva_rate')
                        ->label(__('usage-billing::billing.plan.fields.tva_rate'))
                        ->numeric()
                        ->required()
                        ->default(20)
                        ->suffix('%'),
                    TextInput::make('currency')
                        ->label(__('usage-billing::billing.plan.fields.currency'))
                        ->required()
                        ->default(fn (): string => UsageBilling::currency())
                        ->maxLength(3),
                    TextInput::make('trial_days')
                        ->label(__('usage-billing::billing.plan.fields.trial_days'))
                        ->numeric()
                        ->default(0)
                        ->suffix(__('usage-billing::billing.plan.units.days')),
                    TextInput::make('payment_term_days')
                        ->label(__('usage-billing::billing.plan.fields.payment_term_days'))
                        ->numeric()
                        ->default(30)
                        ->suffix(__('usage-billing::billing.plan.units.days')),
                    TextInput::make('grace_days')
                        ->label(__('usage-billing::billing.plan.fields.grace_days'))
                        ->numeric()
                        ->default(0)
                        ->suffix(__('usage-billing::billing.plan.units.days'))
                        ->helperText(__('usage-billing::billing.plan.help.grace_days')),
                ]),

            Section::make(__('usage-billing::billing.plan.sections.modules'))
                ->description(__('usage-billing::billing.plan.help.modules'))
                ->schema([
                    Repeater::make('planModules')
                        ->hiddenLabel()
                        ->relationship()
                        ->addActionLabel(__('usage-billing::billing.plan.actions.add_module'))
                        ->itemLabel(fn (array $state): ?string => static::moduleName($state))
                        ->collapsible()
                        ->collapsed()
                        ->cloneable()
                        ->schema([
                            Grid::make(4)->schema([
                                Select::make('module_id')
                                    ->label(__('usage-billing::billing.plan.fields.module'))
                                    ->options(fn (): array => UsageBilling::query('module')
                                        ->where('is_active', true)
                                        ->pluck('key', 'id')
                                        ->all())
                                    ->required()
                                    ->distinct()
                                    ->searchable()
                                    ->live(),
                                TextInput::make('included_quantity')
                                    ->label(__('usage-billing::billing.plan.fields.included_quantity'))
                                    ->numeric()
                                    ->minValue(0)
                                    ->placeholder(__('usage-billing::billing.plan.placeholders.unlimited'))
                                    ->helperText(__('usage-billing::billing.plan.help.included_quantity')),
                                TextInput::make('unit_price_ht')
                                    ->label(__('usage-billing::billing.plan.fields.unit_price_ht'))
                                    ->numeric()
                                    ->minValue(0)
                                    ->suffix(fn (): string => UsageBilling::currency())
                                    ->placeholder(__('usage-billing::billing.plan.placeholders.blocks'))
                                    ->helperText(__('usage-billing::billing.plan.help.unit_price_ht')),
                                TextInput::make('hard_ceiling')
                                    ->label(__('usage-billing::billing.plan.fields.hard_ceiling'))
                                    ->numeric()
                                    ->minValue(0)
                                    ->placeholder(__('usage-billing::billing.plan.placeholders.no_ceiling'))
                                    ->helperText(__('usage-billing::billing.plan.help.hard_ceiling')),
                            ]),
                        ]),
                ]),

            Section::make(__('usage-billing::billing.plan.sections.availability'))
                ->columns(2)
                ->schema([
                    Toggle::make('is_active')
                        ->label(__('usage-billing::billing.plan.fields.is_active'))
                        ->default(true),
                    Toggle::make('is_public')
                        ->label(__('usage-billing::billing.plan.fields.is_public'))
                        ->default(true)
                        ->helperText(__('usage-billing::billing.plan.help.is_public')),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withCount(['planModules', 'subscriptions']))
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                TextColumn::make('name')
                    ->label(__('usage-billing::billing.plan.fields.name'))
                    ->state(fn (Plan $record): string => $record->displayName())
                    ->description(fn (Plan $record): string => $record->slug)
                    ->searchable(['slug'])
                    ->weight('medium'),
                TextColumn::make('price_ht')
                    ->label(__('usage-billing::billing.plan.fields.price_ht'))
                    ->state(fn (Plan $record): string => number_format((float) $record->price_ht, 2, ',', ' ').' '.$record->currency)
                    ->description(fn (Plan $record): string => __('usage-billing::billing.plan.tva_suffix', [
                        'rate' => rtrim(rtrim((string) $record->tva_rate, '0'), '.'),
                    ]))
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('plan_modules_count')
                    ->label(__('usage-billing::billing.plan.fields.modules_count'))
                    ->badge()
                    ->color('gray')
                    ->alignCenter(),
                TextColumn::make('subscriptions_count')
                    ->label(__('usage-billing::billing.plan.fields.subscribers'))
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'success' : 'gray')
                    ->alignCenter(),
                IconColumn::make('is_active')
                    ->label(__('usage-billing::billing.plan.fields.is_active'))
                    ->boolean()
                    ->alignCenter(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->emptyStateHeading(__('usage-billing::billing.plan.empty.heading'))
            ->emptyStateDescription(__('usage-billing::billing.plan.empty.description'))
            ->emptyStateIcon(Heroicon::OutlinedSquares2x2);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPlans::route('/'),
            'create' => CreatePlan::route('/create'),
            'edit' => EditPlan::route('/{record}/edit'),
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private static function moduleName(array $state): ?string
    {
        $moduleId = $state['module_id'] ?? null;

        if ($moduleId === null) {
            return null;
        }

        $key = UsageBilling::query('module')->whereKey($moduleId)->value('key');

        if (! is_string($key)) {
            return null;
        }

        $pricing = new PlanModule([
            'included_quantity' => $state['included_quantity'] ?? null,
            'unit_price_ht' => $state['unit_price_ht'] ?? null,
            'hard_ceiling' => $state['hard_ceiling'] ?? null,
        ]);

        return $key.' · '.static::pricingSummary($pricing);
    }

    private static function pricingSummary(PlanModule $pricing): string
    {
        if ($pricing->isUnlimited()) {
            return __('usage-billing::billing.plan.summary.unlimited');
        }

        if (! $pricing->billsOverage()) {
            return __('usage-billing::billing.plan.summary.capped', [
                'included' => $pricing->included_quantity,
            ]);
        }

        return __('usage-billing::billing.plan.summary.metered', [
            'included' => $pricing->included_quantity,
            'price' => rtrim(rtrim(number_format((float) $pricing->unit_price_ht, 2, ',', ' '), '0'), ','),
            'currency' => UsageBilling::currency(),
        ]);
    }
}

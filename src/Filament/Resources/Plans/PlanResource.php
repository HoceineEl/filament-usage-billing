<?php

declare(strict_types=1);

namespace HoceineEl\UsageBilling\Filament\Resources\Plans;

use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use HoceineEl\UsageBilling\Enums\ModuleType;
use HoceineEl\UsageBilling\Exceptions\UnknownModuleException;
use HoceineEl\UsageBilling\Filament\Concerns\BelongsToBillingNavigation;
use HoceineEl\UsageBilling\Filament\Resources\Plans\Pages\CreatePlan;
use HoceineEl\UsageBilling\Filament\Resources\Plans\Pages\EditPlan;
use HoceineEl\UsageBilling\Filament\Resources\Plans\Pages\ListPlans;
use HoceineEl\UsageBilling\Models\Module;
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
        return $schema
            ->columns(3)
            ->components([
                Section::make(__('usage-billing::billing.plan.sections.identity'))
                    ->icon(Heroicon::OutlinedTag)
                    ->columnSpan(2)
                    ->columns(2)
                    ->schema([
                        TextInput::make('slug')
                            ->label(__('usage-billing::billing.plan.fields.slug'))
                            ->required()
                            ->alphaDash()
                            ->unique(ignoreRecord: true)
                            ->prefixIcon(Heroicon::OutlinedHashtag)
                            ->helperText(__('usage-billing::billing.plan.help.slug')),
                        TextInput::make('sort_order')
                            ->label(__('usage-billing::billing.plan.fields.sort_order'))
                            ->numeric()
                            ->default(0)
                            ->helperText(__('usage-billing::billing.plan.help.sort_order')),
                        Tabs::make()
                            ->columnSpanFull()
                            ->tabs(collect(static::locales())
                                ->map(fn (string $label, string $locale): Tab => Tab::make($label)
                                    ->schema([
                                        TextInput::make("name.{$locale}")
                                            ->label(__('usage-billing::billing.plan.fields.name'))
                                            ->required($locale === array_key_first(static::locales())),
                                        Textarea::make("description.{$locale}")
                                            ->label(__('usage-billing::billing.plan.fields.description'))
                                            ->rows(3)
                                            ->helperText(__('usage-billing::billing.plan.help.description')),
                                    ]))
                                ->values()
                                ->all()),
                    ]),

                Section::make(__('usage-billing::billing.plan.sections.pricing'))
                    ->icon(Heroicon::OutlinedBanknotes)
                    ->columnSpan(1)
                    ->columns(2)
                    ->schema([
                        TextInput::make('price_ht')
                            ->label(__('usage-billing::billing.plan.fields.price_ht'))
                            ->numeric()
                            ->required()
                            ->default(0)
                            ->live(onBlur: true)
                            ->suffix(fn (): string => UsageBilling::currency()),
                        TextInput::make('term_months')
                            ->label(__('usage-billing::billing.plan.fields.term_months'))
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->default(12)
                            ->live(onBlur: true)
                            ->suffix(__('usage-billing::billing.plan.units.months')),
                        TextInput::make('tva_rate')
                            ->label(__('usage-billing::billing.plan.fields.tva_rate'))
                            ->numeric()
                            ->required()
                            ->default(20)
                            ->live(onBlur: true)
                            ->suffix('%'),
                        TextInput::make('currency')
                            ->label(__('usage-billing::billing.plan.fields.currency'))
                            ->required()
                            ->default(fn (): string => UsageBilling::currency())
                            ->maxLength(3),
                        Text::make(fn (Get $get): string => static::priceBreakdown($get))
                            ->columnSpanFull()
                            ->icon(Heroicon::OutlinedCalculator)
                            ->color('primary'),
                        TextInput::make('trial_days')
                            ->label(__('usage-billing::billing.plan.fields.trial_days'))
                            ->numeric()
                            ->default(0)
                            ->suffix(__('usage-billing::billing.plan.units.days')),
                        TextInput::make('renewal_notice_days')
                            ->label(__('usage-billing::billing.plan.fields.renewal_notice_days'))
                            ->numeric()
                            ->default(30)
                            ->suffix(__('usage-billing::billing.plan.units.days'))
                            ->helperText(__('usage-billing::billing.plan.help.renewal_notice_days')),
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
                    ->icon(Heroicon::OutlinedSquares2x2)
                    ->description(__('usage-billing::billing.plan.help.modules'))
                    ->columnSpanFull()
                    ->schema([
                        Repeater::make('planModules')
                            ->hiddenLabel()
                            ->relationship()
                            ->addActionLabel(__('usage-billing::billing.plan.actions.add_module'))
                            ->cloneable()
                            ->compact()
                            ->table([
                                TableColumn::make(__('usage-billing::billing.plan.fields.module'))
                                    ->markAsRequired()
                                    ->width('22%'),
                                TableColumn::make(__('usage-billing::billing.plan.fields.included_quantity'))
                                    ->width('16%'),
                                TableColumn::make(__('usage-billing::billing.plan.fields.unit_price_ht'))
                                    ->width('18%'),
                                TableColumn::make(__('usage-billing::billing.plan.fields.hard_ceiling'))
                                    ->width('16%'),
                                TableColumn::make(__('usage-billing::billing.plan.fields.summary')),
                            ])
                            ->schema([
                                Select::make('module_id')
                                    ->hiddenLabel()
                                    ->options(fn (): array => static::moduleOptions())
                                    ->required()
                                    ->distinct()
                                    ->searchable()
                                    ->live(),
                                TextInput::make('included_quantity')
                                    ->hiddenLabel()
                                    ->numeric()
                                    ->minValue(0)
                                    ->live(onBlur: true)
                                    ->placeholder(__('usage-billing::billing.plan.placeholders.unlimited')),
                                TextInput::make('unit_price_ht')
                                    ->hiddenLabel()
                                    ->numeric()
                                    ->minValue(0)
                                    ->live(onBlur: true)
                                    ->suffix(fn (): string => UsageBilling::currency())
                                    ->placeholder(__('usage-billing::billing.plan.placeholders.blocks')),
                                TextInput::make('hard_ceiling')
                                    ->hiddenLabel()
                                    ->numeric()
                                    ->minValue(0)
                                    ->live(onBlur: true)
                                    ->placeholder(__('usage-billing::billing.plan.placeholders.no_ceiling')),
                                Text::make(fn (Get $get): string => static::moduleSummary($get))
                                    ->badge()
                                    ->color(fn (Get $get): string => $get('included_quantity') === null || $get('included_quantity') === ''
                                        ? 'success'
                                        : 'gray'),
                            ]),
                    ]),

                Section::make(__('usage-billing::billing.plan.sections.availability'))
                    ->icon(Heroicon::OutlinedEye)
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        Toggle::make('is_active')
                            ->label(__('usage-billing::billing.plan.fields.is_active'))
                            ->default(true)
                            ->onIcon(Heroicon::Check)
                            ->offIcon(Heroicon::XMark),
                        Toggle::make('is_public')
                            ->label(__('usage-billing::billing.plan.fields.is_public'))
                            ->default(true)
                            ->onIcon(Heroicon::Check)
                            ->offIcon(Heroicon::XMark)
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
     * What the row currently grants, restated in words next to the numbers
     * that produced it.
     */
    private static function moduleSummary(Get $get): string
    {
        $module = static::findModule($get('module_id'));

        if (! $module instanceof Module) {
            return '—';
        }

        $pricing = new PlanModule([
            'included_quantity' => blank($get('included_quantity')) ? null : (int) $get('included_quantity'),
            'unit_price_ht' => blank($get('unit_price_ht')) ? null : (float) $get('unit_price_ht'),
            'hard_ceiling' => blank($get('hard_ceiling')) ? null : (int) $get('hard_ceiling'),
        ]);

        return $pricing->summaryLabel(static::moduleType($module));
    }

    /**
     * The term price restated the two ways a seller is asked about it: what
     * lands on the invoice, and what it works out to per month.
     */
    private static function priceBreakdown(Get $get): string
    {
        $price = (float) $get('price_ht');
        $months = max(1, (int) $get('term_months'));
        $tva = (float) $get('tva_rate');

        return __('usage-billing::billing.plan.price_breakdown', [
            'ttc' => number_format($price * (1 + $tva / 100), 2, ',', ' '),
            'monthly' => number_format($price / $months, 2, ',', ' '),
            'currency' => $get('currency') ?: UsageBilling::currency(),
            'months' => $months,
        ]);
    }

    /** @return array<int, string> */
    private static function moduleOptions(): array
    {
        return UsageBilling::query('module')
            ->where('is_active', true)
            ->orderBy('key')
            ->get()
            ->mapWithKeys(fn (Module $module): array => [
                $module->getKey() => static::moduleOptionLabel($module),
            ])
            ->all();
    }

    private static function moduleOptionLabel(Module $module): string
    {
        try {
            return "{$module->key} — {$module->label()}";
        } catch (UnknownModuleException) {
            return (string) $module->key;
        }
    }

    private static function findModule(mixed $moduleId): ?Module
    {
        if (blank($moduleId)) {
            return null;
        }

        $module = UsageBilling::query('module')->whereKey($moduleId)->first();

        return $module instanceof Module ? $module : null;
    }

    /** A row whose class has gone missing still has to render; it only loses the period hint. */
    private static function moduleType(Module $module): ?ModuleType
    {
        try {
            return $module->type();
        } catch (UnknownModuleException) {
            return null;
        }
    }
}

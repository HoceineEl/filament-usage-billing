<?php

declare(strict_types=1);

namespace HoceineEl\UsageBilling\Filament\Resources\Modules;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use HoceineEl\UsageBilling\Filament\Concerns\BelongsToBillingNavigation;
use HoceineEl\UsageBilling\Filament\Resources\Modules\Pages\ListModules;
use HoceineEl\UsageBilling\Models\Module;
use Illuminate\Database\Eloquent\Builder;

/**
 * Read-only: modules are declared in code and reconciled by a command, so
 * there is nothing here to create or edit by hand.
 */
class ModuleResource extends Resource
{
    use BelongsToBillingNavigation;

    protected static ?string $model = Module::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPuzzlePiece;

    protected static ?int $navigationSort = 2;

    public static function getModelLabel(): string
    {
        return __('usage-billing::billing.module.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('usage-billing::billing.module.plural');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withCount('plans'))
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('key')
                    ->label(__('usage-billing::billing.module.fields.key'))
                    ->state(fn (Module $record): string => static::safeLabel($record))
                    ->description(fn (Module $record): string => $record->key)
                    ->searchable()
                    ->weight('medium'),
                TextColumn::make('class')
                    ->label(__('usage-billing::billing.module.fields.class'))
                    ->color('gray')
                    ->size('sm')
                    ->limit(60)
                    ->tooltip(fn (Module $record): string => $record->class),
                TextColumn::make('plans_count')
                    ->label(__('usage-billing::billing.module.fields.plans_count'))
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'success' : 'warning')
                    ->alignCenter(),
                IconColumn::make('is_active')
                    ->label(__('usage-billing::billing.module.fields.is_active'))
                    ->boolean()
                    ->alignCenter(),
            ])
            ->emptyStateHeading(__('usage-billing::billing.module.empty.heading'))
            ->emptyStateDescription(__('usage-billing::billing.module.empty.description'));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListModules::route('/'),
        ];
    }

    /**
     * A module row can outlive the class it points at — after a rename, say —
     * so the table falls back to the key rather than blowing up the page.
     */
    private static function safeLabel(Module $record): string
    {
        try {
            return $record->label();
        } catch (\Throwable) {
            return $record->key;
        }
    }
}

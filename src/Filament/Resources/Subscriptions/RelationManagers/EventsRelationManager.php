<?php

declare(strict_types=1);

namespace HoceineEl\UsageBilling\Filament\Resources\Subscriptions\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use HoceineEl\UsageBilling\Models\SubscriptionEvent;
use Illuminate\Database\Eloquent\Model;

/**
 * The audit trail. This is what gets opened the day a cabinet argues about a
 * facture, so it stays plain and chronological.
 */
class EventsRelationManager extends RelationManager
{
    protected static string $relationship = 'events';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('usage-billing::billing.event.plural');
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50])
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('usage-billing::billing.event.fields.at'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('type')
                    ->label(__('usage-billing::billing.event.fields.type'))
                    ->badge()
                    ->color('gray'),
                TextColumn::make('change')
                    ->label(__('usage-billing::billing.event.fields.change'))
                    ->state(fn (SubscriptionEvent $record): string => static::describe($record))
                    ->wrap(),
            ])
            ->emptyStateHeading(__('usage-billing::billing.event.empty.heading'));
    }

    private static function describe(SubscriptionEvent $record): string
    {
        $meta = $record->meta ?? [];

        if (isset($meta['module'], $meta['threshold'])) {
            return __('usage-billing::billing.event.threshold_line', [
                'module' => $meta['module'],
                'threshold' => $meta['threshold'],
                'period' => $meta['period'] ?? '',
            ]);
        }

        return match (true) {
            $record->from_value !== null && $record->to_value !== null => "{$record->from_value} → {$record->to_value}",
            $record->to_value !== null => (string) $record->to_value,
            default => '—',
        };
    }
}

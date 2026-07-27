<?php

declare(strict_types=1);

namespace HoceineEl\UsageBilling\Filament\Resources\Modules\Pages;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use HoceineEl\UsageBilling\Filament\Resources\Modules\ModuleResource;
use HoceineEl\UsageBilling\Services\ModuleRegistry;

class ListModules extends ListRecords
{
    protected static string $resource = ModuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sync')
                ->label(__('usage-billing::billing.module.actions.sync'))
                ->icon(Heroicon::ArrowPath)
                ->color('gray')
                ->action(function (): void {
                    $result = app(ModuleRegistry::class)->sync();

                    Notification::make()
                        ->success()
                        ->title(__('usage-billing::billing.module.notifications.synced'))
                        ->body(__('usage-billing::billing.module.notifications.synced_body', $result))
                        ->send();
                }),
        ];
    }
}

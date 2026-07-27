<?php

declare(strict_types=1);

namespace HoceineEl\UsageBilling\Filament\Resources\Plans\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use HoceineEl\UsageBilling\Filament\Resources\Plans\PlanResource;
use HoceineEl\UsageBilling\Models\Plan;

class EditPlan extends EditRecord
{
    protected static string $resource = PlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                // Deleting a plan that still has subscribers would orphan them
                // mid-period with no pricing to bill against.
                ->hidden(fn (Plan $record): bool => $record->subscriptions()->exists()),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

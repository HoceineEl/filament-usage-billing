<?php

declare(strict_types=1);

namespace HoceineEl\UsageBilling\Filament\Resources\Plans\Pages;

use Filament\Resources\Pages\CreateRecord;
use HoceineEl\UsageBilling\Filament\Resources\Plans\PlanResource;

class CreatePlan extends CreateRecord
{
    protected static string $resource = PlanResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

<?php

declare(strict_types=1);

namespace HoceineEl\UsageBilling\Filament\Concerns;

trait BelongsToBillingNavigation
{
    public static function getNavigationGroup(): ?string
    {
        $group = config('usage-billing.filament.navigation_group');

        return is_string($group) ? $group : __('usage-billing::billing.nav.group');
    }

    /**
     * @return array<string, string>
     */
    protected static function locales(): array
    {
        /** @var array<string, string> $locales */
        $locales = config('usage-billing.filament.locales', ['en' => 'English']);

        return $locales;
    }
}

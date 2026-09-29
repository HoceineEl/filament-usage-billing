<?php

declare(strict_types=1);

namespace HoceineEl\UsageBilling\Tests\Fixtures;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use HoceineEl\UsageBilling\Filament\UsageBillingPlugin;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class TestPanelProvider extends PanelProvider
{
    public static bool $ownerOnly = false;

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('app')
            ->path('app')
            ->default()
            ->login()
            ->tenant(Cabinet::class)
            ->pages([Dashboard::class])
            ->plugin(UsageBillingPlugin::make()
                ->tenantBilling()
                ->canAccessBillingUsing(fn (): bool => ! static::$ownerOnly || (bool) auth()->user()?->is_owner))
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                ShareErrorsFromSession::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([Authenticate::class]);
    }
}

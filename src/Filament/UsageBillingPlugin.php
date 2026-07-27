<?php

declare(strict_types=1);

namespace HoceineEl\UsageBilling\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use HoceineEl\UsageBilling\Filament\Resources\Invoices\InvoiceResource;
use HoceineEl\UsageBilling\Filament\Resources\Modules\ModuleResource;
use HoceineEl\UsageBilling\Filament\Resources\Payments\PaymentResource;
use HoceineEl\UsageBilling\Filament\Resources\Plans\PlanResource;
use HoceineEl\UsageBilling\Filament\Resources\Subscriptions\SubscriptionResource;

/**
 * Registers the platform-side billing screens: plans and their pricing,
 * subscriptions, invoices, and the payment queue an operator works through.
 */
class UsageBillingPlugin implements Plugin
{
    /** @var array<int, class-string> */
    protected array $resources = [
        PlanResource::class,
        ModuleResource::class,
        SubscriptionResource::class,
        InvoiceResource::class,
        PaymentResource::class,
    ];

    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'usage-billing';
    }

    /**
     * @param  array<int, class-string>  $resources
     */
    public function resources(array $resources): static
    {
        $this->resources = $resources;

        return $this;
    }

    public function register(Panel $panel): void
    {
        $panel->resources($this->resources);
    }

    public function boot(Panel $panel): void {}
}

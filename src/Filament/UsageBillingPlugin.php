<?php

declare(strict_types=1);

namespace HoceineEl\UsageBilling\Filament;

use Closure;
use Filament\Contracts\Plugin;
use Filament\Facades\Filament;
use Filament\Panel;
use HoceineEl\UsageBilling\Filament\Pages\TenantBilling;
use HoceineEl\UsageBilling\Filament\Resources\Invoices\InvoiceResource;
use HoceineEl\UsageBilling\Filament\Resources\Modules\ModuleResource;
use HoceineEl\UsageBilling\Filament\Resources\Payments\PaymentResource;
use HoceineEl\UsageBilling\Filament\Resources\Plans\PlanResource;
use HoceineEl\UsageBilling\Filament\Resources\Subscriptions\SubscriptionResource;
use HoceineEl\UsageBilling\Http\Middleware\EnsureBillingActive;
use Illuminate\Database\Eloquent\Model;

/**
 * Registers the platform-side billing screens: plans and their pricing,
 * subscriptions, invoices, and the payment queue an operator works through.
 *
 * With `tenantBilling()` it registers the subscriber-facing billing page
 * instead, for the panel the subscriber itself works in.
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

    protected bool $isTenantBilling = false;

    /** @var class-string<TenantBilling> */
    protected string $billingPage = TenantBilling::class;

    protected ?Closure $subscriberResolver = null;

    protected ?Closure $billingAccess = null;

    protected bool $requiresUsableSubscription = true;

    /** @var array<int, string> */
    protected array $exemptRoutes = [];

    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(app(static::class)->getId());

        return $plugin;
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

    /**
     * Register the subscriber's own billing page rather than the operator
     * resources, and lock the panel behind it while the subscription is
     * unusable.
     */
    public function tenantBilling(bool $condition = true): static
    {
        $this->isTenantBilling = $condition;

        return $this;
    }

    /**
     * @param  class-string<TenantBilling>  $page
     */
    public function billingPage(string $page): static
    {
        $this->billingPage = $page;

        return $this;
    }

    /** Who is billed on this panel. Defaults to the current Filament tenant. */
    public function resolveSubscriberUsing(?Closure $resolver): static
    {
        $this->subscriberResolver = $resolver;

        return $this;
    }

    /**
     * Who may open the billing page and pay, such as the account owner only.
     * Everyone else gets a lock message instead of a redirect.
     */
    public function canAccessBillingUsing(?Closure $callback): static
    {
        $this->billingAccess = $callback;

        return $this;
    }

    public function requireUsableSubscription(bool $condition = true): static
    {
        $this->requiresUsableSubscription = $condition;

        return $this;
    }

    /**
     * Route names, wildcards allowed, that stay reachable while the panel is
     * locked.
     *
     * @param  array<int, string>  $routes
     */
    public function exemptRoutes(array $routes): static
    {
        $this->exemptRoutes = $routes;

        return $this;
    }

    public function isTenantBilling(): bool
    {
        return $this->isTenantBilling;
    }

    /**
     * @return class-string<TenantBilling>
     */
    public function getBillingPage(): string
    {
        return $this->billingPage;
    }

    public function resolveSubscriber(): ?Model
    {
        $subscriber = $this->subscriberResolver === null
            ? Filament::getTenant()
            : ($this->subscriberResolver)();

        return $subscriber instanceof Model ? $subscriber : null;
    }

    public function canAccessBilling(): bool
    {
        return $this->billingAccess === null || (bool) ($this->billingAccess)();
    }

    /**
     * @return array<int, string>
     */
    public function getExemptRoutes(): array
    {
        return $this->exemptRoutes;
    }

    public function register(Panel $panel): void
    {
        if (! $this->isTenantBilling) {
            $panel->resources($this->resources);

            return;
        }

        $panel->pages([$this->billingPage]);

        if ($this->requiresUsableSubscription) {
            $panel->tenantMiddleware([EnsureBillingActive::class], isPersistent: true);
        }
    }

    public function boot(Panel $panel): void {}
}

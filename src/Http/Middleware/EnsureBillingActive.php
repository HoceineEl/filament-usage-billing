<?php

declare(strict_types=1);

namespace HoceineEl\UsageBilling\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use HoceineEl\UsageBilling\Contracts\Subscribable;
use HoceineEl\UsageBilling\Enums\SubscriptionStatus;
use HoceineEl\UsageBilling\Filament\UsageBillingPlugin;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Locks a subscriber's panel while its subscription grants no access.
 *
 * A subscriber passes while trialing or active, and while an unpaid invoice is
 * still inside its grace window. After that it keeps its plan but is sent to
 * the billing page until it settles. Anyone not allowed to pay gets a 403
 * explaining why, since a redirect would only lead them to a page they cannot
 * open.
 */
class EnsureBillingActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $plugin = $this->plugin();
        $subscriber = $plugin?->resolveSubscriber();

        if (! $subscriber instanceof Subscribable || ! $subscriber->usageLimitsEnforced()) {
            return $next($request);
        }

        $subscription = $subscriber->currentSubscription();

        if ($subscription?->isUsable() === true || $this->isExempt($request, $plugin)) {
            return $next($request);
        }

        $message = static::lockMessage($subscription?->status);

        abort_unless($plugin->canAccessBilling(), 403, $message);

        Notification::make()
            ->title($message)
            ->danger()
            ->persistent()
            ->send();

        return redirect($plugin->getBillingPage()::getUrl());
    }

    public static function lockMessage(?SubscriptionStatus $status): string
    {
        return __('usage-billing::billing.tenant.locked.'.match ($status) {
            null => 'no_subscription',
            SubscriptionStatus::PendingPayment => 'pending_payment',
            SubscriptionStatus::PastDue => 'past_due',
            SubscriptionStatus::Suspended => 'suspended',
            SubscriptionStatus::Cancelled => 'cancelled',
            default => 'expired',
        });
    }

    protected function isExempt(Request $request, UsageBillingPlugin $plugin): bool
    {
        $panel = Filament::getCurrentPanel()?->getId();

        return $request->routeIs(
            $plugin->getBillingPage()::getRouteName(),
            "filament.{$panel}.auth.*",
            "filament.{$panel}.tenant.*",
            ...$plugin->getExemptRoutes(),
        );
    }

    protected function plugin(): ?UsageBillingPlugin
    {
        $panel = Filament::getCurrentPanel();

        if ($panel === null || ! $panel->hasPlugin('usage-billing')) {
            return null;
        }

        $plugin = $panel->getPlugin('usage-billing');

        return $plugin instanceof UsageBillingPlugin && $plugin->isTenantBilling() ? $plugin : null;
    }
}

<?php

declare(strict_types=1);

namespace HoceineEl\UsageBilling\Tests\Fixtures;

use Carbon\CarbonImmutable;
use HoceineEl\UsageBilling\Models\Invoice;
use HoceineEl\UsageBilling\Models\Subscription;
use HoceineEl\UsageBilling\Services\TermBiller;
use Illuminate\Support\Facades\Cache;

/**
 * Mirrors the 0.1 application override that 0.2 absorbed, so upgrading an app still carrying it stays safe.
 */
final class LegacyTermBiller extends TermBiller
{
    public function issueTermInvoice(Subscription $subscription, ?CarbonImmutable $termStart = null): ?Invoice
    {
        return Cache::lock("usage-billing:term-invoice:{$subscription->getKey()}", 30)->block(
            10,
            fn (): ?Invoice => $this->openTermInvoice($subscription) ?? parent::issueTermInvoice($subscription, $termStart),
        );
    }

    public function openTermInvoice(Subscription $subscription): ?Invoice
    {
        return $subscription->invoices()
            ->awaitingPayment()
            ->oldest('issued_at')
            ->get()
            ->first(fn (Invoice $invoice): bool => self::termOf($invoice) !== null);
    }

    /**
     * @return array{starts_at: CarbonImmutable, ends_at: CarbonImmutable}|null
     */
    public static function termOf(Invoice $invoice): ?array
    {
        return parent::termOf($invoice);
    }

    public function nextTermStart(Subscription $subscription): CarbonImmutable
    {
        return $this->firstTrialEnd($subscription)?->addDay()->startOfDay()
            ?? parent::nextTermStart($subscription);
    }

    private function firstTrialEnd(Subscription $subscription): ?CarbonImmutable
    {
        return $subscription->onTrial() && $subscription->ends_at === null
            ? $subscription->trial_ends_at
            : null;
    }
}

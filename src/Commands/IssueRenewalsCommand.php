<?php

declare(strict_types=1);

namespace HoceineEl\UsageBilling\Commands;

use HoceineEl\UsageBilling\Contracts\InvoiceRenderer;
use HoceineEl\UsageBilling\Enums\SubscriptionStatus;
use HoceineEl\UsageBilling\Models\Invoice;
use HoceineEl\UsageBilling\Models\Subscription;
use HoceineEl\UsageBilling\Services\TermBiller;
use HoceineEl\UsageBilling\UsageBilling;
use Illuminate\Console\Command;
use Throwable;

/**
 * Invoices the next term before the current one runs out, and invoices a trial
 * before it ends, so a subscriber always has a facture in hand with time to pay
 * it by bank transfer.
 *
 * Safe to re-run: TermBiller returns the outstanding term invoice rather than
 * writing a second one.
 */
class IssueRenewalsCommand extends Command
{
    protected $signature = 'usage-billing:issue-renewals
        {--days= : Override the notice window, in days}
        {--subscription=* : Restrict to specific subscription ids}
        {--dry-run : Report what would be invoiced without issuing anything}';

    protected $description = 'Issue term invoices for subscriptions and trials approaching their end';

    public function handle(TermBiller $biller): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $override = $this->option('days');

        $issued = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($this->candidates() as $subscription) {
            $notice = $override !== null
                ? (int) $override
                : (int) ($subscription->plan?->renewal_notice_days ?? 30);

            if (! $this->isDue($subscription, $notice)) {
                $skipped++;

                continue;
            }

            try {
                if ($dryRun) {
                    $this->line(sprintf(
                        '  <fg=gray>#%d</> %s — %s',
                        $subscription->getKey(),
                        $subscription->plan?->displayName() ?? '?',
                        $subscription->ends_at?->toDateString() ?? $subscription->trial_ends_at?->toDateString() ?? 'now',
                    ));
                    $issued++;

                    continue;
                }

                $invoice = $biller->issueTermInvoice($subscription);

                if (! $invoice instanceof Invoice) {
                    $skipped++;

                    continue;
                }

                $this->renderPdf($invoice);
                $issued++;
            } catch (Throwable $exception) {
                $failed++;
                report($exception);
                $this->components->error("Subscription #{$subscription->getKey()}: {$exception->getMessage()}");
            }
        }

        $this->components->info("Issued {$issued}, skipped {$skipped}, failed {$failed}.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Subscription>
     */
    private function candidates()
    {
        /** @var array<int, string> $ids */
        $ids = (array) $this->option('subscription');

        return UsageBilling::query('subscription')
            ->with(['plan', 'subscriber'])
            ->whereIn('status', [
                SubscriptionStatus::Active,
                SubscriptionStatus::Trialing,
                SubscriptionStatus::PendingPayment,
            ])
            ->when($ids !== [], fn ($query) => $query->whereIn('id', $ids))
            ->get();
    }

    /**
     * A subscription is due when its term (or trial) ends inside the notice
     * window, or when it has never been paid for at all.
     */
    private function isDue(Subscription $subscription, int $noticeDays): bool
    {
        if ($subscription->status === SubscriptionStatus::PendingPayment) {
            return true;
        }

        $endsAt = $subscription->ends_at ?? $subscription->trial_ends_at;

        if ($endsAt === null) {
            return true;
        }

        return $endsAt->isBefore(now()->addDays($noticeDays));
    }

    private function renderPdf(Invoice $invoice): void
    {
        try {
            $invoice->forceFill(['pdf_path' => app(InvoiceRenderer::class)->render($invoice)])->save();
        } catch (Throwable $exception) {
            // The facture exists and can be re-rendered on demand; a missing
            // document is worth a warning, not a lost invoice.
            report($exception);
            $this->components->warn("Could not render PDF for invoice #{$invoice->getKey()}.");
        }
    }
}

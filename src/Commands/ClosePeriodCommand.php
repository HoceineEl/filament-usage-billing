<?php

declare(strict_types=1);

namespace HoceineEl\UsageBilling\Commands;

use HoceineEl\UsageBilling\Contracts\InvoiceRenderer;
use HoceineEl\UsageBilling\Enums\InvoiceStatus;
use HoceineEl\UsageBilling\Models\Invoice;
use HoceineEl\UsageBilling\Models\Subscription;
use HoceineEl\UsageBilling\Services\InvoiceBuilder;
use HoceineEl\UsageBilling\Support\Period;
use HoceineEl\UsageBilling\UsageBilling;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Throwable;

/**
 * Closes a calendar month: recompute usage from the modules, then bill it.
 *
 * Safe to re-run. Each subscription's invoice is unique per period, so a batch
 * that dies halfway can simply be started again.
 */
class ClosePeriodCommand extends Command
{
    protected $signature = 'usage-billing:close-period
        {--period= : The YYYY-MM month to close, defaulting to the one just ended}
        {--subscription=* : Restrict to specific subscription ids}
        {--dry-run : Compute and report without writing invoices}';

    protected $description = 'Recompute usage for a closed month and issue the resulting invoices';

    public function handle(InvoiceBuilder $builder): int
    {
        $period = $this->option('period') === null
            ? Period::previous()
            : Period::fromKey((string) $this->option('period'));

        $dryRun = (bool) $this->option('dry-run');

        if ($period->isCurrent()) {
            $this->components->warn(
                "Closing [{$period->key}] before it has ended will bill a partial month."
            );
        }

        $subscriptions = $this->subscriptions($period);

        if ($subscriptions->isEmpty()) {
            $this->components->info("No subscriptions were active during {$period->key}.");

            return self::SUCCESS;
        }

        $issued = 0;
        $skipped = 0;
        $failed = 0;

        $this->components->info(
            ($dryRun ? 'Simulating' : 'Closing')." {$period->key} for {$subscriptions->count()} subscription(s)."
        );

        foreach ($subscriptions as $subscription) {
            try {
                $invoice = $builder->build($subscription, $period, syncUsage: ! $dryRun);

                if (! $invoice instanceof Invoice) {
                    $skipped++;

                    continue;
                }

                if ($dryRun) {
                    $this->line(sprintf(
                        '  <fg=gray>#%d</> %s — %s %s',
                        $subscription->getKey(),
                        $invoice->buyer_name,
                        number_format((float) $invoice->total_ttc, 2),
                        $invoice->currency,
                    ));

                    // A dry run must not leave a draft behind holding the
                    // unique (subscription, period) slot for the real run.
                    $invoice->lines()->delete();
                    $invoice->delete();
                    $issued++;

                    continue;
                }

                if ($invoice->status === InvoiceStatus::Draft) {
                    $builder->issue($invoice);
                    $this->renderPdf($invoice);
                    $issued++;
                } else {
                    $skipped++;
                }
            } catch (Throwable $exception) {
                $failed++;
                report($exception);
                $this->components->error(
                    "Subscription #{$subscription->getKey()}: {$exception->getMessage()}"
                );
            }
        }

        $this->components->info("Issued {$issued}, skipped {$skipped}, failed {$failed}.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return Collection<int, Subscription>
     */
    private function subscriptions(Period $period)
    {
        /** @var array<int, string> $ids */
        $ids = (array) $this->option('subscription');

        return UsageBilling::query('subscription')
            ->with(['plan.planModules.module', 'subscriber'])
            ->overlapping($period->start, $period->end)
            ->when($ids !== [], fn ($query) => $query->whereIn('id', $ids))
            ->get();
    }

    private function renderPdf(Invoice $invoice): void
    {
        try {
            $path = app(InvoiceRenderer::class)->render($invoice);
            $invoice->forceFill(['pdf_path' => $path])->save();
        } catch (Throwable $exception) {
            // A missing document is worth a warning, not a lost invoice: the
            // facture exists and can be re-rendered on demand.
            report($exception);
            $this->components->warn("Could not render PDF for invoice #{$invoice->getKey()}.");
        }
    }
}

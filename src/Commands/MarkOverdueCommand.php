<?php

declare(strict_types=1);

namespace HoceineEl\UsageBilling\Commands;

use HoceineEl\UsageBilling\Enums\InvoiceStatus;
use HoceineEl\UsageBilling\Models\Invoice;
use HoceineEl\UsageBilling\Services\SubscriptionManager;
use HoceineEl\UsageBilling\UsageBilling;
use Illuminate\Console\Command;

class MarkOverdueCommand extends Command
{
    protected $signature = 'usage-billing:mark-overdue';

    protected $description = 'Flag invoices past their due date and move their subscriptions into the grace window';

    public function handle(SubscriptionManager $subscriptions): int
    {
        $invoices = UsageBilling::query('invoice')
            ->with('subscription.plan')
            ->overdue()
            ->get();

        foreach ($invoices as $invoice) {
            /** @var Invoice $invoice */
            $invoice->forceFill(['status' => InvoiceStatus::Overdue])->save();

            if ($invoice->subscription !== null) {
                $subscriptions->markPastDue($invoice->subscription, $invoice);
            }
        }

        $this->components->info("{$invoices->count()} invoice(s) marked overdue.");

        return self::SUCCESS;
    }
}

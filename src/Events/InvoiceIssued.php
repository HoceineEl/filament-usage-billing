<?php

declare(strict_types=1);

namespace HoceineEl\UsageBilling\Events;

use HoceineEl\UsageBilling\Models\Invoice;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InvoiceIssued
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly Invoice $invoice) {}
}

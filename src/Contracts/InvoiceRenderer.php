<?php

declare(strict_types=1);

namespace HoceineEl\UsageBilling\Contracts;

use HoceineEl\UsageBilling\Models\Invoice;

/**
 * Turns an invoice into a stored PDF and returns its path on the configured
 * disk. The package ships an HTML-only default so it can stay free of any PDF
 * dependency; applications bind their own renderer.
 */
interface InvoiceRenderer
{
    public function render(Invoice $invoice): string;
}

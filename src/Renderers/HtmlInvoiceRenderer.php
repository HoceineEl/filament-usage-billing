<?php

declare(strict_types=1);

namespace HoceineEl\UsageBilling\Renderers;

use HoceineEl\UsageBilling\Contracts\InvoiceRenderer;
use HoceineEl\UsageBilling\Models\Invoice;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;

/**
 * Default renderer: writes the invoice view to HTML on the configured disk.
 *
 * The package stays free of any PDF dependency this way. Applications that
 * already own a PDF pipeline bind their own InvoiceRenderer over this one.
 */
class HtmlInvoiceRenderer implements InvoiceRenderer
{
    public function render(Invoice $invoice): string
    {
        $invoice->loadMissing(['lines.module', 'subscription.plan', 'subscriber']);

        $html = View::make('usage-billing::invoice', [
            'invoice' => $invoice,
            'seller' => config('usage-billing.seller'),
        ])->render();

        $path = "usage-billing/invoices/{$invoice->getKey()}.html";

        Storage::disk($this->disk())->put($path, $html);

        return $path;
    }

    private function disk(): string
    {
        return (string) config('usage-billing.invoicing.pdf_disk', 'local');
    }
}

<?php

declare(strict_types=1);

namespace HoceineEl\UsageBilling\Filament\Resources\Invoices\Pages;

use Filament\Resources\Pages\ViewRecord;
use HoceineEl\UsageBilling\Filament\Resources\Invoices\InvoiceResource;

class ViewInvoice extends ViewRecord
{
    protected static string $resource = InvoiceResource::class;

    public function getTitle(): string
    {
        return $this->getRecord()->number
            ?? __('usage-billing::billing.invoice.draft_placeholder');
    }

    protected function getHeaderActions(): array
    {
        return [InvoiceResource::recordPaymentAction()];
    }
}

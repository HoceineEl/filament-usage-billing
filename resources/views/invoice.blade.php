@php
    $locale = app()->getLocale();
    $rtl = in_array($locale, ['ar', 'he', 'fa'], true);
    $money = fn (float|string|null $value): string => number_format((float) $value, 2, ',', ' ').' '.$invoice->currency;
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('usage-billing::billing.invoice.title') }} {{ $invoice->number }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; color: #111827; margin: 0; padding: 32px; }
        h1 { font-size: 22px; margin: 0 0 4px; }
        .muted { color: #6b7280; }
        .parties { display: flex; justify-content: space-between; gap: 32px; margin: 24px 0; }
        .parties > div { flex: 1; }
        .label { font-size: 10px; text-transform: uppercase; letter-spacing: .06em; color: #6b7280; margin-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { padding: 8px 10px; border-bottom: 1px solid #e5e7eb; text-align: {{ $rtl ? 'right' : 'left' }}; }
        th { background: #f9fafb; font-size: 10px; text-transform: uppercase; letter-spacing: .06em; color: #6b7280; }
        .numeric { text-align: {{ $rtl ? 'left' : 'right' }}; white-space: nowrap; }
        .totals { margin-{{ $rtl ? 'right' : 'left' }}: auto; width: 260px; margin-top: 16px; }
        .totals td { border: none; padding: 4px 0; }
        .totals .grand td { border-top: 2px solid #111827; font-weight: 700; font-size: 14px; padding-top: 8px; }
        .annex { margin-top: 36px; page-break-inside: avoid; }
    </style>
</head>
<body>

<h1>{{ __('usage-billing::billing.invoice.title') }} {{ $invoice->number }}</h1>
<div class="muted">
    {{ __('usage-billing::billing.invoice.period') }} {{ $invoice->period }}
    &middot; {{ __('usage-billing::billing.invoice.issued_at') }} {{ $invoice->issued_at?->format('d/m/Y') }}
    &middot; {{ __('usage-billing::billing.invoice.due_at') }} {{ $invoice->due_at?->format('d/m/Y') }}
</div>

<div class="parties">
    <div>
        <div class="label">{{ $seller['name'] }}</div>
        @if ($seller['address']) <div>{{ $seller['address'] }}</div> @endif
        @if ($seller['ice']) <div>{{ __('usage-billing::billing.invoice.ice') }}: {{ $seller['ice'] }}</div> @endif
        @if ($seller['identifiant_fiscal']) <div>{{ __('usage-billing::billing.invoice.identifiant_fiscal') }}: {{ $seller['identifiant_fiscal'] }}</div> @endif
        @if ($seller['email']) <div>{{ $seller['email'] }}</div> @endif
    </div>
    <div>
        <div class="label">{{ __('usage-billing::billing.invoice.billed_to') }}</div>
        <div><strong>{{ $invoice->buyer_name }}</strong></div>
        @if ($invoice->buyer_address) <div>{{ $invoice->buyer_address }}</div> @endif
        @if ($invoice->buyer_ice) <div>{{ __('usage-billing::billing.invoice.ice') }}: {{ $invoice->buyer_ice }}</div> @endif
        @if ($invoice->buyer_identifiant_fiscal) <div>{{ __('usage-billing::billing.invoice.identifiant_fiscal') }}: {{ $invoice->buyer_identifiant_fiscal }}</div> @endif
    </div>
</div>

<table>
    <thead>
        <tr>
            <th>{{ __('usage-billing::billing.invoice.description') }}</th>
            <th class="numeric">{{ __('usage-billing::billing.invoice.quantity') }}</th>
            <th class="numeric">{{ __('usage-billing::billing.invoice.unit_price') }}</th>
            <th class="numeric">{{ __('usage-billing::billing.invoice.amount') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($invoice->lines as $line)
            <tr>
                <td>{{ $line->description }}</td>
                <td class="numeric">{{ rtrim(rtrim(number_format((float) $line->quantity, 2, ',', ' '), '0'), ',') }}</td>
                <td class="numeric">{{ $money($line->unit_price_ht) }}</td>
                <td class="numeric">{{ $money($line->amount_ht) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<table class="totals">
    <tr>
        <td>{{ __('usage-billing::billing.invoice.subtotal_ht') }}</td>
        <td class="numeric">{{ $money($invoice->subtotal_ht) }}</td>
    </tr>
    <tr>
        <td>{{ __('usage-billing::billing.invoice.tva', ['rate' => rtrim(rtrim((string) $invoice->tva_rate, '0'), '.')]) }}</td>
        <td class="numeric">{{ $money($invoice->tva_amount) }}</td>
    </tr>
    <tr class="grand">
        <td>{{ __('usage-billing::billing.invoice.total_ttc') }}</td>
        <td class="numeric">{{ $money($invoice->total_ttc) }}</td>
    </tr>
    @if ((float) $invoice->amount_paid > 0)
        <tr>
            <td>{{ __('usage-billing::billing.invoice.amount_paid') }}</td>
            <td class="numeric">{{ $money($invoice->amount_paid) }}</td>
        </tr>
        <tr>
            <td>{{ __('usage-billing::billing.invoice.balance_due') }}</td>
            <td class="numeric">{{ $money($invoice->balanceDue()) }}</td>
        </tr>
    @endif
</table>

@php
    $annex = collect($invoice->usage_snapshot ?? [])
        ->filter(fn (array $module): bool => ($module['total'] ?? 0) > 0);
@endphp

<div class="annex">
    <div class="label">{{ __('usage-billing::billing.invoice.usage_annex') }}</div>

    @if ($annex->isEmpty())
        <p class="muted">{{ __('usage-billing::billing.invoice.no_usage') }}</p>
    @else
        @foreach ($annex as $module)
            <table>
                <thead>
                    <tr>
                        <th>{{ $module['module_label'] }}</th>
                        <th class="numeric">{{ __('usage-billing::billing.invoice.consumption') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($module['buckets'] as $bucket)
                        <tr>
                            <td>{{ $bucket['attribution_label'] ?? '—' }}</td>
                            <td class="numeric">{{ number_format((float) $bucket['quantity'], 0, ',', ' ') }} {{ $module['unit_label'] }}</td>
                        </tr>
                    @endforeach
                    <tr>
                        <td><strong>{{ __('usage-billing::billing.invoice.subtotal_ht') }}</strong></td>
                        <td class="numeric"><strong>{{ number_format((float) $module['total'], 0, ',', ' ') }} {{ $module['unit_label'] }}</strong></td>
                    </tr>
                </tbody>
            </table>
        @endforeach
    @endif
</div>

</body>
</html>

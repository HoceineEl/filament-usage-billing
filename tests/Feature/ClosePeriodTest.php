<?php

declare(strict_types=1);

use HoceineEl\UsageBilling\Enums\InvoiceStatus;
use HoceineEl\UsageBilling\Models\Invoice;
use HoceineEl\UsageBilling\Services\InvoiceBuilder;
use HoceineEl\UsageBilling\Support\Period;
use HoceineEl\UsageBilling\Tests\Fixtures\Cabinet;
use HoceineEl\UsageBilling\Tests\Fixtures\Customer;

function lastMonth(): Period
{
    return Period::previous();
}

function documentLastMonth(
    Cabinet $cabinet,
    ?Customer $customer = null,
    bool $failed = false,
): void {
    document($cabinet, $customer, $failed, lastMonth()->start->addDay()->toDateTimeString());
}

it('bills the base plan even with no usage', function (): void {
    $cabinet = cabinet();
    $subscription = subscribe($cabinet, planWith(['documents' => ['included' => 10, 'price' => 2]], basePrice: 500));

    $invoice = app(InvoiceBuilder::class)->build($subscription, lastMonth());

    expect($invoice)->toBeInstanceOf(Invoice::class)
        ->and($invoice->lines)->toHaveCount(1)
        ->and((float) $invoice->subtotal_ht)->toBe(500.0)
        ->and((float) $invoice->tva_amount)->toBe(100.0)
        ->and((float) $invoice->total_ttc)->toBe(600.0);
});

it('adds one overage line per module past its allowance', function (): void {
    $cabinet = cabinet();
    $subscription = subscribe($cabinet, planWith(['documents' => ['included' => 2, 'price' => 10]], basePrice: 100));
    $alpha = customer($cabinet, 'Alpha');

    foreach (range(1, 5) as $ignored) {
        documentLastMonth($cabinet, $alpha);
    }

    $invoice = app(InvoiceBuilder::class)->build($subscription, lastMonth());
    $overage = $invoice->lines->firstWhere('module_id', '!=', null);

    expect($invoice->lines)->toHaveCount(2)
        ->and((float) $overage->quantity)->toBe(3.0)
        ->and((float) $overage->amount_ht)->toBe(30.0)
        ->and((float) $invoice->subtotal_ht)->toBe(130.0);
});

it('leaves out usage that never exceeded its allowance', function (): void {
    $cabinet = cabinet();
    $subscription = subscribe($cabinet, planWith(['documents' => ['included' => 10, 'price' => 10]], basePrice: 100));

    documentLastMonth($cabinet);

    $invoice = app(InvoiceBuilder::class)->build($subscription, lastMonth());

    expect($invoice->lines)->toHaveCount(1);
});

it('does not bill a module whose allowance carries no unit price', function (): void {
    $cabinet = cabinet();
    $subscription = subscribe($cabinet, planWith(['documents' => ['included' => 1]], basePrice: 100));

    documentLastMonth($cabinet);
    documentLastMonth($cabinet);

    $invoice = app(InvoiceBuilder::class)->build($subscription, lastMonth());

    expect($invoice->lines)->toHaveCount(1)
        ->and((float) $invoice->subtotal_ht)->toBe(100.0);
});

it('produces nothing when the plan is free and nothing overflowed', function (): void {
    $cabinet = cabinet();
    $subscription = subscribe($cabinet, planWith(['documents' => ['included' => 10, 'price' => 1]], basePrice: 0));

    expect(app(InvoiceBuilder::class)->build($subscription, lastMonth()))->toBeNull();
});

it('freezes the per-client breakdown into the invoice', function (): void {
    $cabinet = cabinet();
    $subscription = subscribe($cabinet, planWith(['documents' => ['included' => 1, 'price' => 5]]));
    $alpha = customer($cabinet, 'Alpha');
    $beta = customer($cabinet, 'Beta');

    documentLastMonth($cabinet, $alpha);
    documentLastMonth($cabinet, $alpha);
    documentLastMonth($cabinet, $beta);

    $invoice = app(InvoiceBuilder::class)->build($subscription, lastMonth());
    $snapshot = collect($invoice->usage_snapshot)->firstWhere('module_key', 'documents');

    expect($snapshot['total'])->toBe(3)
        ->and($snapshot['buckets'])->toHaveCount(2)
        ->and(collect($snapshot['buckets'])->pluck('attribution_label')->all())
        ->toEqualCanonicalizing(['Alpha', 'Beta']);

    $alpha->update(['name' => 'Renamed']);

    expect(collect($invoice->fresh()->usage_snapshot)->firstWhere('module_key', 'documents')['buckets'])
        ->toContain(['quantity' => 2, 'attribution_type' => $alpha->getMorphClass(), 'attribution_id' => $alpha->getKey(), 'attribution_label' => 'Alpha']);
});

it('excludes failed records from the bill', function (): void {
    $cabinet = cabinet();
    $subscription = subscribe($cabinet, planWith(['documents' => ['included' => 0, 'price' => 10]], basePrice: 0));

    documentLastMonth($cabinet);
    documentLastMonth($cabinet, failed: true);

    $invoice = app(InvoiceBuilder::class)->build($subscription, lastMonth());

    expect((float) $invoice->subtotal_ht)->toBe(10.0);
});

it('returns the same invoice instead of a second one when re-run', function (): void {
    $cabinet = cabinet();
    $subscription = subscribe($cabinet, planWith(['documents' => ['included' => 10, 'price' => 1]]));

    $first = app(InvoiceBuilder::class)->build($subscription, lastMonth());
    $second = app(InvoiceBuilder::class)->build($subscription, lastMonth());

    expect($second->getKey())->toBe($first->getKey())
        ->and(Invoice::count())->toBe(1);
});

it('assigns a number only at issue', function (): void {
    $cabinet = cabinet();
    $subscription = subscribe($cabinet, planWith(['documents' => ['included' => 10, 'price' => 1]]));

    $invoice = app(InvoiceBuilder::class)->build($subscription, lastMonth());
    expect($invoice->number)->toBeNull()
        ->and($invoice->status)->toBe(InvoiceStatus::Draft);

    app(InvoiceBuilder::class)->issue($invoice);

    expect($invoice->number)->toMatch('/^FAC-\d{4}-\d{5}$/')
        ->and($invoice->status)->toBe(InvoiceStatus::Issued)
        ->and($invoice->due_at)->not->toBeNull();
});

it('numbers invoices without gaps', function (): void {
    $numbers = collect(range(1, 5))->map(function (int $index): string {
        $cabinet = cabinet(['name' => "Cabinet {$index}"]);
        $subscription = subscribe($cabinet, planWith(['documents' => ['included' => 10, 'price' => 1]]));
        $invoice = app(InvoiceBuilder::class)->build($subscription, lastMonth());

        return app(InvoiceBuilder::class)->issue($invoice)->number;
    });

    expect($numbers->map(fn (string $number): int => (int) substr($number, -5))->all())
        ->toBe([1, 2, 3, 4, 5]);
});

it('leaves no draft behind after a dry run', function (): void {
    $cabinet = cabinet();
    subscribe($cabinet, planWith(['documents' => ['included' => 10, 'price' => 1]]));

    $this->artisan('usage-billing:close-period', [
        '--period' => lastMonth()->key,
        '--dry-run' => true,
    ])->assertSuccessful();

    expect(Invoice::count())->toBe(0);
});

it('issues invoices for every active subscription when closing a month', function (): void {
    foreach (range(1, 3) as $index) {
        $cabinet = cabinet(['name' => "Cabinet {$index}"]);
        subscribe($cabinet, planWith(['documents' => ['included' => 10, 'price' => 1]]));
    }

    $this->artisan('usage-billing:close-period', ['--period' => lastMonth()->key])
        ->assertSuccessful();

    expect(Invoice::where('status', InvoiceStatus::Issued)->count())->toBe(3);
});

it('skips a subscription that started after the period ended', function (): void {
    $cabinet = cabinet();
    subscribe($cabinet, planWith(['documents' => ['included' => 10, 'price' => 1]]), [
        'starts_at' => now()->addMonth(),
    ]);

    $this->artisan('usage-billing:close-period', ['--period' => lastMonth()->key])
        ->assertSuccessful();

    expect(Invoice::count())->toBe(0);
});

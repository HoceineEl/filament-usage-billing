# Filament Usage Billing

Metered, modular subscription billing for multi-tenant Filament applications.

A tenant subscribes to a plan. The plan includes an allowance for each module it
carries. Usage past that allowance bills as an overage line on a monthly
invoice. Usage can be attributed to whatever the application wants to break the
bill down by — a client, a project, a site — without the package knowing what
that thing is.

## The idea

Usage is **derived from your own tables**, not from a counter the application
has to remember to increment.

Each module answers one question: *how much of me did this tenant use between
these two dates, and who was it for?* That query is the billing authority. A
counter cache exists alongside it purely to make allowance bars and limit checks
cheap, and closing a period recomputes it from the module queries before an
invoice is built.

The consequences are worth spelling out:

- A retried job cannot double-bill; there is no increment to replay.
- Work that failed is excluded by the module's own `where`, so no reversal rows.
- Every line on a facture is reproducible from real records months later.

## Installation

```bash
composer require hoceineel/filament-usage-billing
php artisan migrate
```

Publish the config to declare your modules:

```bash
php artisan vendor:publish --tag=usage-billing-config
```

Register the admin screens on a panel:

```php
use HoceineEl\UsageBilling\Filament\UsageBillingPlugin;

$panel->plugins([UsageBillingPlugin::make()]);
```

## Making a model subscribable

```php
use HoceineEl\UsageBilling\Concerns\HasSubscription;
use HoceineEl\UsageBilling\Contracts\BillingParty;
use HoceineEl\UsageBilling\Contracts\Subscribable;

class Organization extends Model implements BillingParty, Subscribable
{
    use HasSubscription;

    public function billingName(): string { return $this->name; }
    public function billingIce(): ?string { return $this->ice; }
    public function billingIdentifiantFiscal(): ?string { return $this->tax_id; }
    public function billingAddress(): ?string { return $this->address; }
    public function billingEmail(): ?string { return $this->email; }
}
```

`BillingParty` is snapshotted onto each invoice at issue, so a later correction
to the record never rewrites a document already sent.

If the host model already has a `subscriptions()` relation — a Cashier
`Billable`, for instance — alias the trait's out of the way. Nothing inside the
trait goes through it:

```php
use Billable, HasSubscription {
    Billable::subscriptions insteadof HasSubscription;
    HasSubscription::subscriptions as usageSubscriptions;
}
```

## Writing a module

```php
class OcrModule implements MeteredModule
{
    public static function key(): string { return 'ocr'; }
    public function label(): string { return 'OCR processing'; }
    public function unitLabel(): string { return 'documents'; }
    public function type(): ModuleType { return ModuleType::Metered; }

    public function usage(Model $subscriber, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return Document::query()
            ->where('organization_id', $subscriber->getKey())
            ->whereBetween('created_at', [$from, $to])
            ->where('status', '!=', DocumentStatus::Failed)
            ->get()
            ->groupBy('client_id')
            ->map(fn ($rows, $clientId) => UsageBucket::for(Client::find($clientId), $rows->count()))
            ->values();
    }
}
```

List it in `usage-billing.modules`, then:

```bash
php artisan usage-billing:sync-modules
```

`ModuleType::Snapshot` is for things that are a state rather than a stream — a
seat count, say. Those are measured at the end of the period, not summed across
it.

## Pricing

Each plan carries a row per module. Four shapes, and they are exhaustive:

| `included_quantity` | `unit_price_ht` | `hard_ceiling` | Behaviour |
|---|---|---|---|
| `null` | — | — | Unlimited, never billed |
| `N` | set | `null` | Soft overage at the unit price, never blocks |
| `N` | set | `M` | Overage billed from N to M, blocked past M |
| `N` | `null` | — | Blocked at N, no overage possible |

A module absent from a plan means the feature is off for its subscribers.

## Gating and metering

```php
$organization->canUse('ocr');                       // bool
$organization->ensureCanUse('ocr');                 // throws ModuleUnavailableException
$organization->ensureCanUseQuantity('ocr', 40);     // for batches
$organization->meter('ocr', 1, $client);            // refresh the counter cache
$organization->usageSummary();                      // authoritative, recomputed
```

Check capacity for the **whole batch** before dispatching one. A single free
slot must not wave through fifty jobs.

`ModuleUnavailableException::userMessage()` is phrased for the reason the caller
was denied — no subscription, module not in plan, ceiling reached, subscription
inactive — so the UI can say something useful instead of "error".

Applications with an open-beta switch override `usageLimitsEnforced()` on the
subscriber to make every gate permit usage while still recording it.

## Closing a month

```bash
php artisan usage-billing:close-period --dry-run
php artisan usage-billing:close-period
php artisan usage-billing:mark-overdue
```

Closing recomputes every counter from the module queries, builds one invoice per
subscription, freezes the per-attribution breakdown into `usage_snapshot`, draws
a gapless number from the yearly sequence and renders a PDF.

Idempotent on `(subscription, period)`, so a batch that dies halfway is simply
run again. `--dry-run` reports without consuming an invoice number.

Overage is priced at subscriber level, not per attribution: the allowance is one
pool. The breakdown is reporting, printed as an annex.

## Invoices and payments

Numbers are assigned at issue, never at draft, so the yearly sequence has no
gaps — a requirement in several jurisdictions.

Payments are declared by the subscriber and validated by an operator who has
seen the money arrive. Settling every outstanding invoice returns a `PastDue`
subscription to `Active`; settling only the newest does not.

The package ships an HTML `InvoiceRenderer` so it depends on no PDF library.
Bind your own to use an existing pipeline:

```php
$this->app->singleton(InvoiceRenderer::class, MyPdfRenderer::class);
```

## Testing

```bash
composer test
```

## License

MIT.

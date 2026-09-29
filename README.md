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

Register the operator screens (plans, modules, subscriptions, invoices,
payments) on your central panel:

```php
use HoceineEl\UsageBilling\Filament\UsageBillingPlugin;

$panel->plugins([UsageBillingPlugin::make()]);
```

Supports Laravel 12 and 13 on Filament 5.

## Scheduling

Terms are prepaid: the renewal facture goes out before a term ends, and access
follows payment. Schedule the daily jobs:

```php
Schedule::command('usage-billing:issue-renewals')->dailyAt('02:30')->withoutOverlapping();
Schedule::command('usage-billing:end-trials')->dailyAt('02:40')->withoutOverlapping();
Schedule::command('usage-billing:expire-subscriptions')->dailyAt('02:45')->withoutOverlapping();
Schedule::command('usage-billing:mark-overdue')->dailyAt('03:00')->withoutOverlapping();
// Only when plans bill overage on top of the term price:
Schedule::command('usage-billing:close-period')->monthlyOn(1, '04:00')->withoutOverlapping();
```

- `issue-renewals` invoices the next term inside the plan's renewal notice
  window, and a trial before it ends. A subscription keeps **one open term
  facture** at a time, whatever month the run lands in, and the run skips any
  subscription whose facture is already out.
- `end-trials` ends a trial on its `trial_ends_at` instead of letting it run
  until the facture falls overdue. An unpaid trial moves to `PastDue` with the
  plan's grace window, counted from the trial end or the facture's due date,
  whichever is later.
- `expire-subscriptions` walks an unrenewed term through grace into `Expired`.
- `mark-overdue` flags unpaid factures and opens the grace window.

The first paid term after a free trial starts the day after the trial ends,
and the trial facture falls due on the trial's last day.

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

## Enforcement

Enforcement is **off by default** so a new install can meter and invoice in
shadow before anyone is refused. While it is off every gate allows, usage is
still recorded, and the tenant billing page never locks the panel.

Turn it on for every subscriber:

```dotenv
USAGE_BILLING_ENFORCE=true
```

or per subscriber, by overriding `usageLimitsEnforced()` — handy for an open
beta or a grandfathered account:

```php
public function usageLimitsEnforced(): bool
{
    return ! $this->is_beta_tester && config('usage-billing.enforcement.enabled');
}
```

## Daily allowances

Allowances reset on the calendar month by default. A metered module can reset
every day instead by implementing `HasResetPeriod`:

```php
use HoceineEl\UsageBilling\Contracts\HasResetPeriod;
use HoceineEl\UsageBilling\Enums\ResetPeriod;

class AiRepliesModule implements HasResetPeriod, MeteredModule
{
    public function resetPeriod(): ResetPeriod { return ResetPeriod::Day; }
    // ...
}
```

The plan's `included_quantity` then means "per day". Gates, `usageFor()`,
`remainingFor()`, threshold notices and the tenant page all measure today's
window, read straight from the module's `usage()` query. Invoicing stays
monthly: overage for a daily module is summed day by day over the month.

Days roll over at the application timezone unless the subscriber implements
`HasUsageTimezone`:

```php
public function usageTimezone(): string { return $this->timezone ?? 'Africa/Casablanca'; }
```

`$subscriber->usageWindow('ai_replies')` returns the current window (`key`,
`start`, `end`).

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

## Tenant billing page

Register the plugin on the panel your subscribers use with `tenantBilling()`.
It registers only the subscriber-facing page — no operator resources — and
locks the panel behind it while the subscription grants no access:

```php
UsageBillingPlugin::make()
    ->tenantBilling()
    ->canAccessBillingUsing(fn (): bool => auth()->user()->isOwner())
    ->exemptRoutes(['filament.app.pages.support'])
```

| Option | Default | |
|---|---|---|
| `resolveSubscriberUsing(Closure)` | `Filament::getTenant()` | Who is billed on this panel |
| `canAccessBillingUsing(Closure)` | everyone | Who may open the page and pay; others get a 403 with the lock reason |
| `billingPage(class-string)` | `TenantBilling` | Swap in a subclass |
| `requireUsableSubscription(bool)` | `true` | Register the lock middleware |
| `exemptRoutes(array)` | `[]` | Extra route names (wildcards allowed) reachable while locked |

The page shows the plan and term, usage bars per module, the outstanding
facture with the seller's bank details (`usage-billing.seller.rib`,
`bank_name`), and the subscriber's invoices with **Pay online** (when a
gateway is configured), **I have paid** (declares an offline payment with an
optional receipt), and invoice/receipt downloads.

`EnsureBillingActive` is added as persistent tenant middleware. It lets the
billing page, the panel's `auth.*` and `tenant.*` routes and your exempt
routes through, redirects a locked subscriber to the page, and does nothing
while enforcement is off. On a panel without tenancy, add
`HoceineEl\UsageBilling\Http\Middleware\EnsureBillingActive` to
`authMiddleware()` yourself and pass `resolveSubscriberUsing()`.

Receipts are stored on `usage-billing.invoicing.receipts_disk` (default
`local`, which is private) under `receipts_directory`, and are only served
through Livewire actions scoped to the subscriber's own invoices or the
operator panel.

## Payment gateways

Payment stays offline (bank transfer, cheque) until a gateway is configured.
The package defines the contract and the plumbing, not a provider:

```php
interface PaymentGateway
{
    public function checkout(Invoice $invoice): string;               // redirect URL
    public function handleWebhook(Request $request): ?GatewayPayment; // verify, then describe the payment
}
```

Register a provider and make it the default:

```php
app(PaymentGatewayManager::class)->extend('stripe', fn ($app) => new StripeGateway(...));
```

```dotenv
USAGE_BILLING_GATEWAY=stripe
```

Webhooks arrive at `POST /usage-billing/webhooks/{gateway}` (route
`usage-billing.webhooks`, no session or CSRF). The gateway verifies the
signature — throw `InvalidWebhookSignatureException` to answer 403 — and
returns a `GatewayPayment` (invoice id, amount, provider reference). The
package records it as a card payment and runs the normal `validatePayment()`
flow, so a paid term facture opens the term exactly as an operator
validation would. Redelivered webhooks are idempotent on the reference.
Return `null` for events that confirm no payment. Set
`usage-billing.gateways.webhook_path` to `null` to skip the route.

`FakeGateway` (driver `fake`) signs webhooks with an HMAC of the body in the
`X-Usage-Billing-Signature` header, for tests and local development.

## Operator actions

The admin resources carry the day-to-day fixes, each also available as an
action class for your own screens:

| Action class | Admin action | |
|---|---|---|
| `CancelInvoiceAction` | Invoices: cancel (row, bulk, view page) | Annuls an unpaid facture, keeps its number, settles the subscription |
| `ExtendTrialAction` | Subscriptions: extend trial | Moves the trial end and the open trial facture's due date |
| `GrantBillingGraceAction` | Subscriptions: grant grace | Reopens a locked subscription for N days (`grace.admin_days`) |
| `GetPublicPlansAction` | — | Public catalogue for pricing pages, flags `recommended_plan` |

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

## Upgrading from 0.1

See [CHANGELOG.md](CHANGELOG.md#020).

## Testing

```bash
composer test
```

## License

MIT.

# Changelog

All notable changes to `hoceineel/filament-usage-billing` are documented here.

## 0.2.0

### Added

- **Laravel 13** support alongside Laravel 12 (`illuminate/contracts ^12.0|^13.0`, `orchestra/testbench ^10.0|^11.0`).
- **Tenant billing page.** `UsageBillingPlugin::make()->tenantBilling()` registers the subscriber-facing `TenantBilling` page (plan, term, usage bars, outstanding facture with bank details, invoices, pay online, declare payment with a private receipt upload, invoice and receipt downloads) and no operator resources. Options: `resolveSubscriberUsing()`, `canAccessBillingUsing()`, `billingPage()`, `requireUsableSubscription()`, `exemptRoutes()`.
- **`EnsureBillingActive` middleware**, registered as persistent tenant middleware by `tenantBilling()`: redirects a locked subscriber to the billing page, lets the page, `auth.*`, `tenant.*` and exempt routes through, answers 403 to members who cannot pay.
- **Daily allowances.** `ResetPeriod` enum and optional `HasResetPeriod` module contract; optional `HasUsageTimezone` subscriber contract. Gates, `usageFor()`, `remainingFor()`, threshold notices and the tenant page measure today's window. Monthly invoicing is unchanged; overage for a daily module is summed day by day. New `usageWindow()` on the trait and `UsageWindow` value object.
- **Payment gateway contract.** `PaymentGateway` (`checkout()`, `handleWebhook()`), `PaymentGatewayManager` (Laravel `Manager`, extend with your provider), `GatewayPayment`, `InvalidWebhookSignatureException`, and the `POST /usage-billing/webhooks/{gateway}` route, which records the payment and runs `validatePayment()`. Idempotent on the provider reference. Ships a `FakeGateway` only.
- `SubscriptionManager::recordGatewayPayment()`.
- **`usage-billing:end-trials`** command: ends unpaid trials on `trial_ends_at` and opens the plan's grace window (from the trial end or the facture's due date, whichever is later).
- **Operator actions**: `CancelInvoiceAction` (admin row, bulk and view-page cancel), `ExtendTrialAction` and `GrantBillingGraceAction` (admin subscription actions), `GetPublicPlansAction` for pricing pages.
- `TermBiller::openTermInvoice()` and `TermBiller::termOf()`.
- `SubscriptionEventType::InvoiceCancelled`.
- Config: `gateways.*`, `grace.admin_days`, `recommended_plan`, `invoicing.receipts_directory`. All have defaults, so a config published from 0.1 keeps working.
- Translations for all of the above in English, French and Arabic.

### Changed

- `TermBiller::issueTermInvoice()` takes a per-subscription lock and returns the **open term facture whatever its month**, instead of only one issued in the same calendar month. This stops an unpaid trial from being billed a new term every month.
- The first term after a free trial starts the day after the trial ends, and the trial facture falls due on the trial's last day at the latest.
- Term and upgrade factures now dispatch `InvoiceIssued`, like period invoices already did.
- `usage-billing:issue-renewals` skips subscriptions whose term facture is already out.
- `PlanModule::summaryLabel()` reads "per day" for daily modules (new optional `$resetPeriod` argument).
- `ModuleUsageSummary` gained optional trailing `resetPeriod` and `overage` constructor arguments.

### Deprecated

- `TermBiller::outstandingTermInvoice()`; use `openTermInvoice()`.

### Upgrading from 0.1 (Gedify)

No migrations were added. `composer require hoceineel/filament-usage-billing:^0.2` and the app keeps working with its 0.1 overrides in place; the steps below remove code the package now owns.

1. **`App\Billing\PrepaidTermBiller`** — the lock, the one-open-facture rule, the trial-aware start and due dates and the `InvoiceIssued` dispatch are now package defaults. Delete the class and its `TermBiller` binding. If you keep the production seller-identity guard, move it into a small subclass that overrides the protected `createTermInvoice()` and returns `null` when the seller is incomplete. Kept unchanged, it still works (its lock key differs from the package's), but new term factures dispatch `InvoiceIssued` twice; `SendBillingNoticeAction` already de-duplicates by invoice, so no double notice goes out.
2. **`App\Console\Commands\Billing\IssueTermRenewalsCommand`** — the package command now skips subscriptions with an open term facture. Delete it and its `IssueRenewalsCommand` binding.
3. **`billing:end-expired-trials`** — replace it in the schedule with `usage-billing:end-trials` (same rules). Running both is harmless: the second finds nothing to do.
4. **Invoice cancellation** — `App\Actions\Billing\CancelUsageInvoiceAction` maps to `HoceineEl\UsageBilling\Actions\CancelInvoiceAction` (same `canCancel()` / `execute($invoice, $reason, $actor)`). Delete `App\Filament\Admin\Resources\Invoices\*` and go back to `UsageBillingPlugin::make()` (or add the package `InvoiceResource` to your `resources([...])`). While the override stays, the cancel action shows twice on the table and view page.
5. **`ExtendTrialAction` / `GrantBillingGraceAction`** — the package versions take a `Subscription`. Keep Gedify's wrappers for the Paddle-era organization fields and delegate the subscription part to the package actions.
6. **`GetPublicPlansAction`** — the package version lists every module under `modules` (keyed by module key, with `reset_period` and `summary`) and in `features`. Gedify's version separates seat modules into `clients` / `team`; keep it, or derive those from `modules['active_clients']['allowance']`.
7. **Tenant page** — Gedify's `UsageSubscription` page and `EnsureBillingActive` middleware keep working. To adopt the package's: add `UsageBillingPlugin::make()->tenantBilling()->canAccessBillingUsing(fn (): bool => (bool) auth()->user()?->isOwner())` to the accountant panel, drop the app middleware from `tenantMiddleware()`, and point links at `TenantBilling::getUrl()` (route `filament.accountant.pages.billing`). Staff who cannot pay get a 403 with the lock reason rather than `CabinetAccessBlockedException`; keep the app middleware if that exception's screen matters.
8. **Webhook route** — `POST /usage-billing/webhooks/{gateway}` is registered by default and answers 404 while no gateway is configured. Set `usage-billing.gateways.webhook_path` to `null` to skip it.
9. **Enforcement** is unchanged: still off unless `USAGE_BILLING_ENFORCE=true` or `usageLimitsEnforced()` says otherwise.

## 0.1.1

- Plan allowances are labelled by their meter, and the plan form was rebuilt.

## 0.1.0

- Initial release.

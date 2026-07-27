<?php

declare(strict_types=1);

use HoceineEl\UsageBilling\Models\Invoice;
use HoceineEl\UsageBilling\Models\InvoiceLine;
use HoceineEl\UsageBilling\Models\InvoiceSequence;
use HoceineEl\UsageBilling\Models\Module;
use HoceineEl\UsageBilling\Models\Payment;
use HoceineEl\UsageBilling\Models\Plan;
use HoceineEl\UsageBilling\Models\PlanModule;
use HoceineEl\UsageBilling\Models\Subscription;
use HoceineEl\UsageBilling\Models\SubscriptionEvent;
use HoceineEl\UsageBilling\Models\UsageCounter;

return [

    /*
    |--------------------------------------------------------------------------
    | Metered modules
    |--------------------------------------------------------------------------
    |
    | Every class listed here must implement the MeteredModule contract. Run
    | `usage-billing:sync-modules` after changing this list so the database
    | registry (which plan pricing rows point at) matches the code.
    |
    */

    'modules' => [
        // \App\Billing\Modules\OcrModule::class,
    ],

    'models' => [
        'plan' => Plan::class,
        'module' => Module::class,
        'plan_module' => PlanModule::class,
        'subscription' => Subscription::class,
        'usage_counter' => UsageCounter::class,
        'invoice' => Invoice::class,
        'invoice_line' => InvoiceLine::class,
        'payment' => Payment::class,
        'invoice_sequence' => InvoiceSequence::class,
        'subscription_event' => SubscriptionEvent::class,
    ],

    'tables' => [
        'plan' => 'ub_plans',
        'module' => 'ub_modules',
        'plan_module' => 'ub_plan_modules',
        'subscription' => 'ub_subscriptions',
        'usage_counter' => 'ub_usage_counters',
        'invoice' => 'ub_invoices',
        'invoice_line' => 'ub_invoice_lines',
        'payment' => 'ub_payments',
        'invoice_sequence' => 'ub_invoice_sequences',
        'subscription_event' => 'ub_subscription_events',
    ],

    /*
    |--------------------------------------------------------------------------
    | Invoicing
    |--------------------------------------------------------------------------
    |
    | `number_format` receives the fiscal year and the zero-padded sequence.
    | Numbers are assigned when an invoice is issued, never while it is a
    | draft, so the yearly sequence stays gapless.
    |
    */

    'invoicing' => [
        'number_prefix' => env('USAGE_BILLING_NUMBER_PREFIX', 'FAC'),
        'number_format' => ':prefix-:year-:sequence',
        'sequence_padding' => 5,
        'currency' => env('USAGE_BILLING_CURRENCY', 'MAD'),
        'tva_rate' => env('USAGE_BILLING_TVA_RATE', 20),
        'payment_term_days' => 30,
        'receipts_disk' => env('USAGE_BILLING_RECEIPTS_DISK', 'local'),
        'pdf_disk' => env('USAGE_BILLING_PDF_DISK', 'local'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Seller identity
    |--------------------------------------------------------------------------
    |
    | The platform's own legal identity, printed on every facture it issues.
    |
    */

    'seller' => [
        'name' => env('USAGE_BILLING_SELLER_NAME', env('APP_NAME')),
        'ice' => env('USAGE_BILLING_SELLER_ICE'),
        'identifiant_fiscal' => env('USAGE_BILLING_SELLER_IF'),
        'rc' => env('USAGE_BILLING_SELLER_RC'),
        'address' => env('USAGE_BILLING_SELLER_ADDRESS'),
        'email' => env('USAGE_BILLING_SELLER_EMAIL'),
        'phone' => env('USAGE_BILLING_SELLER_PHONE'),
        'bank_name' => env('USAGE_BILLING_SELLER_BANK_NAME'),
        'rib' => env('USAGE_BILLING_SELLER_RIB'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Usage thresholds
    |--------------------------------------------------------------------------
    |
    | Percentages of a module's included allowance at which the subscriber is
    | notified. Each threshold fires at most once per module per period.
    |
    */

    'thresholds' => [80, 100],

    /*
    |--------------------------------------------------------------------------
    | Enforcement
    |--------------------------------------------------------------------------
    |
    | The cutover switch. While this is off, gates permit everything and usage
    | is still recorded, so a month can be metered and invoiced in shadow before
    | anyone is refused. Turn it on once the numbers have been checked against
    | a real period.
    |
    */

    'enforcement' => [
        'enabled' => env('USAGE_BILLING_ENFORCE', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Filament
    |--------------------------------------------------------------------------
    |
    | `locales` drives the per-language inputs on plan names. `navigation_group`
    | is where the admin resources file themselves.
    |
    */

    'filament' => [
        'locales' => ['fr' => 'Français', 'ar' => 'العربية', 'en' => 'English'],
        'navigation_group' => null,
        'navigation_sort' => 80,
    ],

    /*
    |--------------------------------------------------------------------------
    | Counter cache
    |--------------------------------------------------------------------------
    |
    | The counter cache only serves live allowance bars and ceiling checks. It
    | is never the billing authority: closing a period recomputes every counter
    | from the module queries before an invoice is built.
    |
    */

    'cache' => [
        'store' => env('USAGE_BILLING_CACHE_STORE'),
        'ttl' => 300,
        'prefix' => 'usage-billing',
    ],

];

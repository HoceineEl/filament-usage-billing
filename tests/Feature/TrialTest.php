<?php

declare(strict_types=1);

use HoceineEl\UsageBilling\Enums\InvoiceStatus;
use HoceineEl\UsageBilling\Enums\SubscriptionStatus;
use HoceineEl\UsageBilling\Events\InvoiceIssued;
use HoceineEl\UsageBilling\Models\Invoice;
use HoceineEl\UsageBilling\Models\Subscription;
use HoceineEl\UsageBilling\Services\TermBiller;
use HoceineEl\UsageBilling\Tests\Fixtures\LegacyTermBiller;
use Illuminate\Support\Facades\Event;

function trialing(int $trialDays = 14): Subscription
{
    return subscribe(cabinet(), annualPlan(), [
        'status' => SubscriptionStatus::Trialing,
        'starts_at' => now(),
        'trial_ends_at' => now()->addDays($trialDays),
    ]);
}

it('starts the first paid term the day after the trial ends', function (): void {
    $subscription = trialing();

    $invoice = app(TermBiller::class)->issueTermInvoice($subscription);
    $term = TermBiller::termOf($invoice);

    expect($term['starts_at']->toDateString())->toBe(now()->addDays(15)->toDateString())
        ->and($invoice->due_at->toDateString())->toBe(now()->addDays(14)->toDateString());
});

it('keeps a single open term facture across months', function (): void {
    $subscription = trialing();
    $first = app(TermBiller::class)->issueTermInvoice($subscription);

    $this->travel(2)->months();

    $second = app(TermBiller::class)->issueTermInvoice($subscription->refresh());

    expect($second->getKey())->toBe($first->getKey())
        ->and(Invoice::count())->toBe(1);
});

it('skips subscriptions whose term facture is already out on the renewal run', function (): void {
    $subscription = trialing();
    app(TermBiller::class)->issueTermInvoice($subscription);

    $this->travel(1)->month();
    $this->artisan('usage-billing:issue-renewals')->assertSuccessful();

    expect(Invoice::count())->toBe(1);
});

it('announces a new term facture', function (): void {
    Event::fake([InvoiceIssued::class]);

    app(TermBiller::class)->issueTermInvoice(trialing());

    Event::assertDispatchedTimes(InvoiceIssued::class, 1);
});

it('ignores upgrade supplements when looking for the open term facture', function (): void {
    $subscription = subscribe(cabinet(), annualPlan(price: 1000), ['status' => SubscriptionStatus::PendingPayment]);
    settle(app(TermBiller::class)->issueTermInvoice($subscription));

    app(TermBiller::class)->issueUpgradeInvoice($subscription->refresh(), annualPlan(price: 2000));

    expect(app(TermBiller::class)->openTermInvoice($subscription))->toBeNull();
});

it('ends an unpaid trial on the day it runs out', function (): void {
    $subscription = trialing();
    app(TermBiller::class)->issueTermInvoice($subscription);

    $this->travel(15)->days();
    $this->artisan('usage-billing:end-trials')->assertSuccessful();
    $subscription->refresh();

    expect($subscription->status)->toBe(SubscriptionStatus::PastDue)
        ->and($subscription->grace_ends_at->toDateString())
        ->toBe($subscription->trial_ends_at->addDays(10)->toDateString())
        ->and($subscription->isUsable())->toBeTrue();
});

it('counts the grace window from a facture due after the trial', function (): void {
    $subscription = trialing();
    $invoice = app(TermBiller::class)->issueTermInvoice($subscription);
    $invoice->forceFill(['due_at' => now()->addDays(30)])->save();

    $this->travel(15)->days();
    $this->artisan('usage-billing:end-trials')->assertSuccessful();

    expect($subscription->refresh()->grace_ends_at->toDateString())
        ->toBe($invoice->due_at->addDays(10)->toDateString());
});

it('leaves a running trial alone', function (): void {
    $subscription = trialing();

    $this->artisan('usage-billing:end-trials')->assertSuccessful();

    expect($subscription->refresh()->status)->toBe(SubscriptionStatus::Trialing);
});

it('activates the term bought at the end of a trial once it is paid', function (): void {
    $subscription = trialing();
    $invoice = app(TermBiller::class)->issueTermInvoice($subscription);

    settle($invoice);

    expect($invoice->refresh()->status)->toBe(InvoiceStatus::Paid)
        ->and($subscription->refresh()->status)->toBe(SubscriptionStatus::Active)
        ->and($subscription->ends_at->toDateString())->toBe(TermBiller::termOf($invoice)['ends_at']->toDateString());
});

it('still honours an application override written against 0.1', function (): void {
    app()->singleton(TermBiller::class, LegacyTermBiller::class);
    $subscription = trialing();

    $first = app(TermBiller::class)->issueTermInvoice($subscription);
    $second = app(TermBiller::class)->issueTermInvoice($subscription);

    expect($second->getKey())->toBe($first->getKey())
        ->and(TermBiller::termOf($first)['starts_at']->toDateString())->toBe(now()->addDays(15)->toDateString());
});

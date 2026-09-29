@php
    $subscription = $this->getSubscription();
    $lockReason = $this->getLockReason();
    $supportEmail = $this->getSupportEmail();
@endphp

<x-filament-panels::page>
    @if ($lockReason)
        <x-filament::callout
            color="danger"
            icon="heroicon-o-lock-closed"
            :heading="__('usage-billing::billing.tenant.locked.heading')"
            :description="$lockReason"
        >
            @if ($supportEmail)
                <x-slot name="controls">
                    <x-filament::button tag="a" href="mailto:{{ $supportEmail }}" color="danger" size="sm" icon="heroicon-m-envelope">
                        {{ __('usage-billing::billing.tenant.locked.contact_support') }}
                    </x-filament::button>
                </x-slot>
            @endif
        </x-filament::callout>
    @endif

    @if (! $subscription)
        <x-filament::empty-state
            icon="heroicon-o-credit-card"
            icon-color="gray"
            :heading="__('usage-billing::billing.tenant.empty.heading')"
            :description="__('usage-billing::billing.tenant.empty.description')"
        />
    @else
        @php
            $rows = $this->getUsageRows();
            $breakdown = $this->getBreakdown();
            $outstanding = $this->getOutstandingInvoice();
            $daysLeft = $this->getDaysUntilRenewal();
            $bank = $this->getBankDetails();
        @endphp

        @if ($subscription->awaitsFirstPayment())
            <x-filament::callout
                color="warning"
                icon="heroicon-o-clock"
                :heading="__('usage-billing::billing.tenant.term.awaiting_heading')"
                :description="__('usage-billing::billing.tenant.term.awaiting_description')"
            />
        @elseif ($subscription->onTrial())
            <x-filament::callout
                color="info"
                icon="heroicon-o-sparkles"
                :heading="__('usage-billing::billing.tenant.term.trial_heading', ['date' => $subscription->trial_ends_at->translatedFormat('d/m/Y')])"
                :description="__('usage-billing::billing.tenant.term.trial_description')"
            />
        @elseif ($this->isRenewalApproaching())
            <x-filament::callout
                color="primary"
                icon="heroicon-o-arrow-path"
                :heading="trans_choice('usage-billing::billing.tenant.term.renewal_heading', $daysLeft, ['count' => $daysLeft])"
                :description="$outstanding
                    ? __('usage-billing::billing.tenant.term.renewal_invoiced')
                    : __('usage-billing::billing.tenant.term.renewal_description')"
            />
        @endif

        @if ($outstanding)
            <x-filament::callout
                color="gray"
                icon="heroicon-o-building-library"
                :heading="__('usage-billing::billing.tenant.payment_instructions.heading', ['number' => $outstanding->number])"
                :description="__($bank
                    ? 'usage-billing::billing.tenant.payment_instructions.description'
                    : 'usage-billing::billing.tenant.payment_instructions.contact', [
                    'amount' => $this->money($outstanding->balanceDue(), $outstanding->currency),
                    'date' => $outstanding->due_at?->translatedFormat('d/m/Y') ?? '—',
                ])"
            >
                @if ($bank)
                    <x-slot name="footer">
                        <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <div class="space-y-1">
                                <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('usage-billing::billing.tenant.payment_instructions.beneficiary') }}</dt>
                                <dd class="text-sm font-medium text-gray-950 dark:text-white">{{ $bank['beneficiary'] }}</dd>
                            </div>
                            <div class="space-y-1">
                                <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('usage-billing::billing.tenant.payment_instructions.bank') }}</dt>
                                <dd class="text-sm font-medium text-gray-950 dark:text-white">{{ $bank['bank'] ?? '—' }}</dd>
                            </div>
                            <div class="space-y-1">
                                <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('usage-billing::billing.tenant.payment_instructions.rib') }}</dt>
                                <dd class="break-all text-sm font-medium tabular-nums text-gray-950 dark:text-white" dir="ltr">{{ $bank['rib'] }}</dd>
                            </div>
                            <div class="space-y-1">
                                <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('usage-billing::billing.tenant.payment_instructions.reference') }}</dt>
                                <dd class="text-sm font-medium tabular-nums text-gray-950 dark:text-white" dir="ltr">{{ $outstanding->number }}</dd>
                            </div>
                        </dl>
                    </x-slot>
                @endif
            </x-filament::callout>
        @endif

        <div class="grid gap-4 sm:grid-cols-3">
            <x-filament::section>
                <div class="space-y-1">
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('usage-billing::billing.tenant.summary.plan') }}</p>
                    <p class="text-xl font-semibold text-gray-950 dark:text-white">{{ $subscription->plan?->displayName() }}</p>
                    <x-filament::badge :color="$subscription->status->getColor()" class="mt-1 w-fit">
                        {{ $subscription->status->getLabel() }}
                    </x-filament::badge>
                </div>
            </x-filament::section>

            <x-filament::section>
                <div class="space-y-1">
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('usage-billing::billing.tenant.summary.price') }}</p>
                    <p class="text-xl font-semibold text-gray-950 dark:text-white">{{ $this->money($subscription->plan?->price_ht) }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        {{ trans_choice('usage-billing::billing.tenant.summary.per_term', (int) $subscription->plan?->term_months, ['months' => (int) $subscription->plan?->term_months]) }}
                    </p>
                </div>
            </x-filament::section>

            <x-filament::section>
                <div class="space-y-1">
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('usage-billing::billing.tenant.summary.term_ends') }}</p>
                    <p class="text-xl font-semibold text-gray-950 dark:text-white">{{ $subscription->ends_at?->translatedFormat('d/m/Y') ?? '—' }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        {{ $daysLeft !== null
                            ? trans_choice('usage-billing::billing.tenant.summary.days_left', $daysLeft, ['count' => $daysLeft])
                            : __('usage-billing::billing.tenant.summary.not_started') }}
                    </p>
                </div>
            </x-filament::section>
        </div>

        @if ($rows->isNotEmpty())
            <x-filament::section
                :heading="__('usage-billing::billing.tenant.modules.heading')"
                :description="__('usage-billing::billing.tenant.modules.description')"
            >
                <div class="divide-y divide-gray-100 dark:divide-white/10">
                    @foreach ($rows as $row)
                        @php
                            $daily = $row['reset_period'] === \HoceineEl\UsageBilling\Enums\ResetPeriod::Day;
                            $barColor = match (true) {
                                $row['over'] > 0 => 'bg-danger-500',
                                $row['near_limit'] => 'bg-warning-500',
                                default => 'bg-primary-500',
                            };
                        @endphp

                        <div class="flex flex-col gap-2 py-3 first:pt-0 last:pb-0">
                            <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                                <span class="text-sm font-medium text-gray-950 dark:text-white">
                                    {{ $row['label'] }}
                                    <span class="text-xs font-normal text-gray-500 dark:text-gray-400">
                                        · {{ __($daily ? 'usage-billing::billing.tenant.modules.today' : 'usage-billing::billing.tenant.modules.this_month') }}
                                    </span>
                                </span>

                                <span class="text-sm tabular-nums text-gray-500 dark:text-gray-400">
                                    {{ number_format($row['used'], 0, ',', ' ') }}
                                    @if ($row['allowance'] !== null)
                                        / {{ number_format($row['allowance'], 0, ',', ' ') }}
                                    @endif
                                    <span class="text-xs">{{ $row['unit'] }}</span>
                                </span>
                            </div>

                            @if ($row['percent'] !== null)
                                <div class="h-1.5 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-white/10">
                                    <div class="h-full rounded-full {{ $barColor }}" style="width: {{ $row['percent'] }}%"></div>
                                </div>
                            @endif

                            <div class="flex flex-wrap items-center justify-between gap-x-4 text-xs">
                                <span class="text-gray-500 dark:text-gray-400">
                                    @if ($row['allowance'] === null)
                                        {{ __('usage-billing::billing.tenant.modules.unlimited') }}
                                    @elseif ($row['over'] > 0)
                                        {{ __('usage-billing::billing.tenant.modules.over_by', ['count' => number_format($row['over'], 0, ',', ' '), 'unit' => $row['unit']]) }}
                                    @else
                                        {{ __('usage-billing::billing.tenant.modules.remaining', ['count' => number_format(max(0, $row['allowance'] - $row['used']), 0, ',', ' '), 'unit' => $row['unit']]) }}
                                    @endif

                                    @if ($daily)
                                        · {{ __('usage-billing::billing.tenant.modules.month_total', ['count' => number_format($row['month_total'], 0, ',', ' '), 'unit' => $row['unit']]) }}
                                    @endif
                                </span>

                                @if ($row['near_limit'])
                                    <span class="font-medium text-warning-600 dark:text-warning-400">
                                        {{ __('usage-billing::billing.tenant.modules.near_limit') }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-filament::section>
        @endif

        @if ($breakdown->isNotEmpty())
            <x-filament::section
                :heading="__('usage-billing::billing.tenant.breakdown.heading')"
                :description="__('usage-billing::billing.tenant.breakdown.description')"
                collapsible
                collapsed
            >
                <div class="divide-y divide-gray-100 dark:divide-white/10">
                    @foreach ($breakdown as $item)
                        <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 py-2.5 first:pt-0 last:pb-0">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-gray-950 dark:text-white">{{ $item['label'] }}</p>
                                <p class="truncate text-xs text-gray-500 dark:text-gray-400">
                                    @foreach ($item['modules'] as $module => $quantity)
                                        {{ $module }} {{ number_format($quantity, 0, ',', ' ') }}@if (! $loop->last) · @endif
                                    @endforeach
                                </p>
                            </div>

                            <span class="text-sm tabular-nums text-gray-500 dark:text-gray-400">{{ number_format($item['total'], 0, ',', ' ') }}</span>
                        </div>
                    @endforeach
                </div>
            </x-filament::section>
        @endif
    @endif

    {{ $this->table }}
</x-filament-panels::page>

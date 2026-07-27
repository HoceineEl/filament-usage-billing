<?php

declare(strict_types=1);

namespace HoceineEl\UsageBilling\Services;

use Carbon\CarbonImmutable;
use HoceineEl\UsageBilling\Models\InvoiceSequence;
use HoceineEl\UsageBilling\UsageBilling;
use Illuminate\Support\Facades\DB;

/**
 * Hands out gapless yearly invoice numbers.
 *
 * Moroccan invoicing rules require an unbroken sequence, so the counter row is
 * taken under `lockForUpdate` and a number is only ever drawn at the moment an
 * invoice is issued — never while it is still a draft that might be discarded.
 */
class InvoiceNumberGenerator
{
    public function next(?CarbonImmutable $date = null): string
    {
        $date ??= CarbonImmutable::now();
        $year = (int) $date->format('Y');
        $prefix = (string) config('usage-billing.invoicing.number_prefix', 'FAC');

        $number = DB::transaction(function () use ($prefix, $year): int {
            /** @var InvoiceSequence $sequence */
            $sequence = UsageBilling::query('invoice_sequence')
                ->lockForUpdate()
                ->firstOrCreate(
                    ['prefix' => $prefix, 'fiscal_year' => $year],
                    ['last_number' => 0],
                );

            $sequence->increment('last_number');

            return (int) $sequence->refresh()->last_number;
        });

        return $this->format($prefix, $year, $number);
    }

    public function format(string $prefix, int $year, int $number): string
    {
        $padding = (int) config('usage-billing.invoicing.sequence_padding', 5);

        return str_replace(
            [':prefix', ':year', ':sequence'],
            [$prefix, (string) $year, str_pad((string) $number, $padding, '0', STR_PAD_LEFT)],
            (string) config('usage-billing.invoicing.number_format', ':prefix-:year-:sequence'),
        );
    }
}

<?php

declare(strict_types=1);

namespace HoceineEl\UsageBilling\Support;

use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Stringable;

/**
 * A calendar month, identified by its `YYYY-MM` key.
 *
 * Allowances reset and invoices close on month boundaries, so every usage
 * figure in the system is scoped by one of these.
 */
final readonly class Period implements Stringable
{
    private function __construct(
        public string $key,
        public CarbonImmutable $start,
        public CarbonImmutable $end,
    ) {}

    public static function fromKey(string $key): self
    {
        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $key) !== 1) {
            throw new InvalidArgumentException("Invalid period key [{$key}], expected YYYY-MM.");
        }

        return self::fromDate(CarbonImmutable::createFromFormat('Y-m-d', "{$key}-01"));
    }

    public static function fromDate(CarbonImmutable $date): self
    {
        $start = $date->startOfMonth();

        return new self($start->format('Y-m'), $start, $start->endOfMonth());
    }

    public static function current(): self
    {
        return self::fromDate(CarbonImmutable::now());
    }

    public static function previous(): self
    {
        return self::fromDate(CarbonImmutable::now()->subMonthNoOverflow());
    }

    public function next(): self
    {
        return self::fromDate($this->start->addMonthNoOverflow());
    }

    public function prior(): self
    {
        return self::fromDate($this->start->subMonthNoOverflow());
    }

    public function contains(CarbonImmutable $date): bool
    {
        return $date->betweenIncluded($this->start, $this->end);
    }

    public function isCurrent(): bool
    {
        return $this->key === self::current()->key;
    }

    public function label(): string
    {
        return $this->start->translatedFormat('F Y');
    }

    public function __toString(): string
    {
        return $this->key;
    }
}

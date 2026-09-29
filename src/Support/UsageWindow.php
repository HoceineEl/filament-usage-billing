<?php

declare(strict_types=1);

namespace HoceineEl\UsageBilling\Support;

use Carbon\CarbonImmutable;
use HoceineEl\UsageBilling\Contracts\HasUsageTimezone;
use HoceineEl\UsageBilling\Enums\ResetPeriod;
use Illuminate\Database\Eloquent\Model;
use Stringable;

/**
 * The stretch of time an allowance covers: a calendar month, or one day in the
 * subscriber's timezone. Bounds are expressed in the application's timezone
 * so they can be handed straight to a query.
 */
final readonly class UsageWindow implements Stringable
{
    private function __construct(
        public string $key,
        public ResetPeriod $resetPeriod,
        public CarbonImmutable $start,
        public CarbonImmutable $end,
    ) {}

    public static function fromPeriod(Period $period): self
    {
        return new self($period->key, ResetPeriod::Month, $period->start, $period->end);
    }

    public static function day(CarbonImmutable $date, ?string $timezone = null): self
    {
        $appTimezone = (string) config('app.timezone', 'UTC');
        $local = $date->setTimezone($timezone ?? $appTimezone);

        return new self(
            $local->format('Y-m-d'),
            ResetPeriod::Day,
            $local->startOfDay()->setTimezone($appTimezone),
            $local->endOfDay()->setTimezone($appTimezone),
        );
    }

    public static function current(ResetPeriod $resetPeriod, Model $subscriber): self
    {
        return match ($resetPeriod) {
            ResetPeriod::Month => self::fromPeriod(Period::current()),
            ResetPeriod::Day => self::day(CarbonImmutable::now(), self::timezoneFor($subscriber)),
        };
    }

    /**
     * Every day window of a month, in the subscriber's timezone.
     *
     * @return array<int, self>
     */
    public static function daysOf(Period $period, Model $subscriber): array
    {
        $timezone = self::timezoneFor($subscriber);
        $days = [];

        for ($date = CarbonImmutable::parse($period->start->format('Y-m-d'), $timezone); $date->format('Y-m') === $period->key; $date = $date->addDay()) {
            $days[] = self::day($date, $timezone);
        }

        return $days;
    }

    public static function timezoneFor(Model $subscriber): string
    {
        return $subscriber instanceof HasUsageTimezone
            ? $subscriber->usageTimezone()
            : (string) config('app.timezone', 'UTC');
    }

    public function __toString(): string
    {
        return $this->key;
    }
}

<?php

declare(strict_types=1);

namespace HoceineEl\UsageBilling;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Resolves the configured model class for each of the package's tables, so
 * applications can swap any model without the package hard-coding names.
 */
final class UsageBilling
{
    /**
     * @return class-string<Model>
     */
    public static function modelClass(string $key): string
    {
        /** @var class-string<Model> $class */
        $class = config("usage-billing.models.{$key}");

        return $class;
    }

    public static function model(string $key): Model
    {
        $class = self::modelClass($key);

        return new $class;
    }

    /**
     * @return Builder<Model>
     */
    public static function query(string $key)
    {
        return self::modelClass($key)::query();
    }

    public static function table(string $key): string
    {
        return (string) config("usage-billing.tables.{$key}");
    }

    public static function currency(): string
    {
        return (string) config('usage-billing.invoicing.currency', 'MAD');
    }

    /**
     * Who can hold a subscription. Declared by the application so the admin
     * panel can offer a picker without the package knowing the tenant model.
     *
     * @return class-string<Model>|null
     */
    public static function subscriberModel(): ?string
    {
        /** @var class-string<Model>|null $class */
        $class = config('usage-billing.subscriber.model');

        return $class;
    }

    /**
     * @return array<int|string, string>
     */
    public static function subscriberOptions(): array
    {
        $class = self::subscriberModel();

        if ($class === null) {
            return [];
        }

        $subscriber = new $class;
        $label = (string) config('usage-billing.subscriber.label_attribute', 'name');

        return $subscriber->newQuery()->orderBy($label)->pluck($label, $subscriber->getKeyName())->all();
    }

    /**
     * @return array<int, int>
     */
    public static function thresholds(): array
    {
        /** @var array<int, int> $thresholds */
        $thresholds = config('usage-billing.thresholds', [80, 100]);

        return $thresholds;
    }
}

<?php

declare(strict_types=1);

namespace HoceineEl\UsageBilling\Models\Concerns;

trait UsesConfiguredTable
{
    public function getTable(): string
    {
        return $this->table ?? (string) config('usage-billing.tables.'.static::configKey());
    }

    abstract public static function configKey(): string;
}

<?php

declare(strict_types=1);

namespace HoceineEl\UsageBilling\Data;

use Illuminate\Support\Collection;

/**
 * A module's standing for one period: what was used, what the plan covers, and
 * what the excess costs. This is what the allowance bars render from and what
 * gets frozen into an invoice's usage snapshot.
 */
final readonly class ModuleUsageSummary
{
    /**
     * @param  Collection<int, UsageBucket>  $buckets
     */
    public function __construct(
        public string $moduleKey,
        public string $moduleLabel,
        public string $unitLabel,
        public int $total,
        public ?int $allowance,
        public ?float $unitPriceHt,
        public ?int $ceiling,
        public Collection $buckets,
    ) {}

    public function overageQuantity(): int
    {
        if ($this->allowance === null) {
            return 0;
        }

        return max(0, $this->total - $this->allowance);
    }

    public function overageAmountHt(): float
    {
        if ($this->unitPriceHt === null) {
            return 0.0;
        }

        return round($this->overageQuantity() * $this->unitPriceHt, 2);
    }

    public function isBillable(): bool
    {
        return $this->overageQuantity() > 0 && $this->unitPriceHt !== null;
    }

    public function consumedPercentage(): ?float
    {
        if ($this->allowance === null || $this->allowance === 0) {
            return null;
        }

        return round($this->total / $this->allowance * 100, 2);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'module_key' => $this->moduleKey,
            'module_label' => $this->moduleLabel,
            'unit_label' => $this->unitLabel,
            'total' => $this->total,
            'allowance' => $this->allowance,
            'unit_price_ht' => $this->unitPriceHt,
            'ceiling' => $this->ceiling,
            'overage_quantity' => $this->overageQuantity(),
            'overage_amount_ht' => $this->overageAmountHt(),
            'buckets' => $this->buckets
                ->map(fn (UsageBucket $bucket): array => $bucket->toArray())
                ->all(),
        ];
    }
}

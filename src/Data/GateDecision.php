<?php

declare(strict_types=1);

namespace HoceineEl\UsageBilling\Data;

use HoceineEl\UsageBilling\Enums\GateReason;

final readonly class GateDecision
{
    private function __construct(
        public GateReason $reason,
        public ?int $used = null,
        public ?int $allowance = null,
        public ?int $ceiling = null,
    ) {}

    public static function allowed(?int $used = null, ?int $allowance = null, ?int $ceiling = null): self
    {
        return new self(GateReason::Allowed, $used, $allowance, $ceiling);
    }

    public static function denied(GateReason $reason, ?int $used = null, ?int $allowance = null, ?int $ceiling = null): self
    {
        return new self($reason, $used, $allowance, $ceiling);
    }

    public function allows(): bool
    {
        return $this->reason === GateReason::Allowed;
    }

    public function denies(): bool
    {
        return ! $this->allows();
    }

    /**
     * Units left before the allowance is exhausted. Null when the module is
     * unlimited or the decision never got far enough to measure usage.
     */
    public function remaining(): ?int
    {
        if ($this->allowance === null || $this->used === null) {
            return null;
        }

        return max(0, $this->allowance - $this->used);
    }

    /**
     * How far into the allowance the subscriber is, as a percentage. Can
     * exceed 100 while overage accrues.
     */
    public function consumedPercentage(): ?float
    {
        if ($this->allowance === null || $this->allowance === 0 || $this->used === null) {
            return null;
        }

        return round($this->used / $this->allowance * 100, 2);
    }

    public function isOverAllowance(): bool
    {
        return $this->allowance !== null
            && $this->used !== null
            && $this->used > $this->allowance;
    }
}

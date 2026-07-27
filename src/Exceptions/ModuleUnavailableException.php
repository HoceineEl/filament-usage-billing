<?php

declare(strict_types=1);

namespace HoceineEl\UsageBilling\Exceptions;

use HoceineEl\UsageBilling\Data\GateDecision;
use HoceineEl\UsageBilling\Enums\GateReason;
use RuntimeException;

class ModuleUnavailableException extends RuntimeException
{
    public function __construct(
        public readonly string $moduleKey,
        public readonly GateDecision $decision,
    ) {
        parent::__construct(
            __('usage-billing::billing.errors.module_unavailable', [
                'module' => $moduleKey,
                'reason' => $decision->reason->value,
            ])
        );
    }

    public function reason(): GateReason
    {
        return $this->decision->reason;
    }

    /**
     * Message to show the subscriber, phrased for the reason they were denied
     * rather than as a generic failure.
     */
    public function userMessage(): string
    {
        return __("usage-billing::billing.denied.{$this->decision->reason->value}", [
            'allowance' => $this->decision->allowance ?? 0,
            'ceiling' => $this->decision->ceiling ?? 0,
            'used' => $this->decision->used ?? 0,
        ]);
    }
}

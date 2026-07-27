<?php

declare(strict_types=1);

namespace HoceineEl\UsageBilling\Enums;

use Filament\Support\Contracts\HasLabel;

enum PaymentMethod: string implements HasLabel
{
    case Virement = 'virement';
    case Cheque = 'cheque';
    case Especes = 'especes';
    case Card = 'card';

    public function getLabel(): string
    {
        return __("usage-billing::billing.payment_method.{$this->value}");
    }

    /**
     * Offline methods are the ones a subscriber declares and an operator has
     * to confirm against a bank statement.
     */
    public function requiresManualValidation(): bool
    {
        return $this !== self::Card;
    }
}

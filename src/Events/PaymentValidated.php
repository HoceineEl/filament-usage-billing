<?php

declare(strict_types=1);

namespace HoceineEl\UsageBilling\Events;

use HoceineEl\UsageBilling\Models\Payment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentValidated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly Payment $payment) {}
}

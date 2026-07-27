<?php

declare(strict_types=1);

namespace HoceineEl\UsageBilling\Tests\Fixtures;

use HoceineEl\UsageBilling\Concerns\HasSubscription;
use HoceineEl\UsageBilling\Contracts\BillingParty;
use HoceineEl\UsageBilling\Contracts\Subscribable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cabinet extends Model implements BillingParty, Subscribable
{
    use HasSubscription;

    protected $table = 'cabinets';

    protected $guarded = [];

    public static bool $limitsEnforced = true;

    /** @return HasMany<Customer, $this> */
    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function billingName(): string
    {
        return (string) $this->name;
    }

    public function billingIce(): ?string
    {
        return $this->ice;
    }

    public function billingIdentifiantFiscal(): ?string
    {
        return $this->identifiant_fiscal;
    }

    public function billingAddress(): ?string
    {
        return $this->address;
    }

    public function billingEmail(): ?string
    {
        return $this->email;
    }

    public function usageLimitsEnforced(): bool
    {
        return static::$limitsEnforced;
    }
}

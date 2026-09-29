<?php

declare(strict_types=1);

namespace HoceineEl\UsageBilling\Tests\Fixtures;

use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Collection;

class User extends Authenticatable implements FilamentUser, HasTenants
{
    protected $table = 'users';

    protected $guarded = [];

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    /** @return Collection<int, Cabinet> */
    public function getTenants(Panel $panel): Collection
    {
        return Cabinet::query()->whereKey($this->cabinet_id)->get();
    }

    public function canAccessTenant(Model $tenant): bool
    {
        return (int) $tenant->getKey() === (int) $this->cabinet_id;
    }
}

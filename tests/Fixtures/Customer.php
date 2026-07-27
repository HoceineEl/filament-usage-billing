<?php

declare(strict_types=1);

namespace HoceineEl\UsageBilling\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Stands in for the consuming application's own client record — the thing
 * usage gets attributed to without the package ever knowing about it.
 */
class Customer extends Model
{
    protected $table = 'customers';

    protected $guarded = [];

    /** @return BelongsTo<Cabinet, $this> */
    public function cabinet(): BelongsTo
    {
        return $this->belongsTo(Cabinet::class);
    }
}

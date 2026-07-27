<?php

declare(strict_types=1);

namespace HoceineEl\UsageBilling\Models;

use HoceineEl\UsageBilling\Enums\PaymentMethod;
use HoceineEl\UsageBilling\Enums\PaymentStatus;
use HoceineEl\UsageBilling\Models\Concerns\UsesConfiguredTable;
use HoceineEl\UsageBilling\UsageBilling;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Payment extends Model
{
    use HasFactory;
    use UsesConfiguredTable;

    protected $guarded = [];

    public static function configKey(): string
    {
        return 'payment';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'method' => PaymentMethod::class,
            'status' => PaymentStatus::class,
            'amount' => 'decimal:2',
            'paid_at' => 'immutable_datetime',
            'validated_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(UsageBilling::modelClass('invoice'));
    }

    /** @return MorphTo<Model, $this> */
    public function submittedBy(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return MorphTo<Model, $this> */
    public function validatedBy(): MorphTo
    {
        return $this->morphTo();
    }

    #[Scope]
    protected function pending(Builder $query): Builder
    {
        return $query->where('status', PaymentStatus::Pending);
    }

    #[Scope]
    protected function validated(Builder $query): Builder
    {
        return $query->where('status', PaymentStatus::Validated);
    }

    public function isPending(): bool
    {
        return $this->status === PaymentStatus::Pending;
    }
}

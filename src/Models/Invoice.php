<?php

declare(strict_types=1);

namespace HoceineEl\UsageBilling\Models;

use HoceineEl\UsageBilling\Enums\InvoiceStatus;
use HoceineEl\UsageBilling\Enums\PaymentStatus;
use HoceineEl\UsageBilling\Models\Concerns\UsesConfiguredTable;
use HoceineEl\UsageBilling\UsageBilling;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Invoice extends Model
{
    use HasFactory;
    use UsesConfiguredTable;

    protected $guarded = [];

    public static function configKey(): string
    {
        return 'invoice';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'subtotal_ht' => 'decimal:2',
            'tva_rate' => 'decimal:2',
            'tva_amount' => 'decimal:2',
            'total_ttc' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'usage_snapshot' => 'array',
            'issued_at' => 'immutable_datetime',
            'due_at' => 'immutable_datetime',
            'sent_at' => 'immutable_datetime',
            'paid_at' => 'immutable_datetime',
        ];
    }

    /** @return MorphTo<Model, $this> */
    public function subscriber(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<Subscription, $this> */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(UsageBilling::modelClass('subscription'));
    }

    /** @return HasMany<InvoiceLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(UsageBilling::modelClass('invoice_line'))->orderBy('sort_order');
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(UsageBilling::modelClass('payment'));
    }

    #[Scope]
    protected function awaitingPayment(Builder $query): Builder
    {
        return $query->whereIn('status', [
            InvoiceStatus::Issued,
            InvoiceStatus::PartiallyPaid,
            InvoiceStatus::Overdue,
        ]);
    }

    #[Scope]
    protected function overdue(Builder $query): Builder
    {
        return $query
            ->whereIn('status', [InvoiceStatus::Issued, InvoiceStatus::PartiallyPaid])
            ->whereNotNull('due_at')
            ->where('due_at', '<', now());
    }

    #[Scope]
    protected function forPeriod(Builder $query, string $period): Builder
    {
        return $query->where('period', $period);
    }

    public function balanceDue(): float
    {
        return round((float) $this->total_ttc - (float) $this->amount_paid, 2);
    }

    public function isFullyPaid(): bool
    {
        return $this->balanceDue() <= 0.0;
    }

    /**
     * Recompute paid amount and status from validated payments, so a rejected
     * or amended payment cannot leave the invoice overstating what it received.
     */
    public function recalculatePayments(): void
    {
        $paid = (float) $this->payments()
            ->where('status', PaymentStatus::Validated)
            ->sum('amount');

        $this->amount_paid = $paid;

        $this->status = match (true) {
            $this->status === InvoiceStatus::Cancelled => InvoiceStatus::Cancelled,
            $paid <= 0.0 && $this->due_at?->isPast() === true => InvoiceStatus::Overdue,
            $paid <= 0.0 => InvoiceStatus::Issued,
            $paid + 0.001 >= (float) $this->total_ttc => InvoiceStatus::Paid,
            default => InvoiceStatus::PartiallyPaid,
        };

        $this->paid_at = $this->status === InvoiceStatus::Paid
            ? ($this->paid_at ?? now()->toImmutable())
            : null;

        $this->save();
    }
}

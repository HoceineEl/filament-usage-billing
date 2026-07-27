<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The buyer identity is snapshotted rather than joined: a cabinet that
     * corrects its ICE next year must not rewrite the factures it already
     * received. `usage_snapshot` freezes the per-attribution breakdown for the
     * same reason.
     */
    public function up(): void
    {
        Schema::create(config('usage-billing.tables.invoice'), function (Blueprint $table): void {
            $table->id();
            $table->morphs('subscriber');
            $table->foreignId('subscription_id')
                ->constrained(config('usage-billing.tables.subscription'))
                ->cascadeOnDelete();
            $table->string('number')->nullable()->unique();
            $table->char('period', 7);
            $table->string('status')->index();

            $table->string('buyer_name');
            $table->string('buyer_ice')->nullable();
            $table->string('buyer_identifiant_fiscal')->nullable();
            $table->text('buyer_address')->nullable();
            $table->string('buyer_email')->nullable();

            $table->char('currency', 3)->default('MAD');
            $table->decimal('subtotal_ht', 12, 2)->default(0);
            $table->decimal('tva_rate', 5, 2)->default(20);
            $table->decimal('tva_amount', 12, 2)->default(0);
            $table->decimal('total_ttc', 12, 2)->default(0);
            $table->decimal('amount_paid', 12, 2)->default(0);

            $table->json('usage_snapshot')->nullable();
            $table->string('pdf_path')->nullable();
            $table->text('notes')->nullable();

            $table->timestamp('issued_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['subscription_id', 'period']);
            $table->index(['subscriber_type', 'subscriber_id'], 'ub_invoices_subscriber_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('usage-billing.tables.invoice'));
    }
};

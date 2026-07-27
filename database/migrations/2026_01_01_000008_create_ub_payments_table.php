<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('usage-billing.tables.payment'), function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_id')
                ->constrained(config('usage-billing.tables.invoice'))
                ->cascadeOnDelete();
            $table->string('method');
            $table->string('status')->index();
            $table->decimal('amount', 12, 2);
            $table->char('currency', 3)->default('MAD');
            $table->string('reference')->nullable();
            $table->string('receipt_path')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->nullableMorphs('submitted_by');
            $table->nullableMorphs('validated_by');
            $table->timestamp('validated_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['invoice_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('usage-billing.tables.payment'));
    }
};

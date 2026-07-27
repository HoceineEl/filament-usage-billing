<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('usage-billing.tables.invoice_line'), function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_id')
                ->constrained(config('usage-billing.tables.invoice'))
                ->cascadeOnDelete();
            $table->foreignId('module_id')
                ->nullable()
                ->constrained(config('usage-billing.tables.module'))
                ->nullOnDelete();
            $table->string('description');
            $table->string('attribution_label')->nullable();
            $table->decimal('quantity', 12, 2)->default(1);
            $table->decimal('unit_price_ht', 12, 4)->default(0);
            $table->decimal('amount_ht', 12, 2)->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['invoice_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('usage-billing.tables.invoice_line'));
    }
};

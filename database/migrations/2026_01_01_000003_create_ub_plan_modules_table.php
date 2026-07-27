<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pricing for one module inside one plan.
     *
     * `included_quantity` null means unlimited and never billed. A null
     * `unit_price_ht` alongside a set allowance means the allowance itself is
     * the wall: usage stops there rather than spilling into overage.
     */
    public function up(): void
    {
        Schema::create(config('usage-billing.tables.plan_module'), function (Blueprint $table): void {
            $table->id();
            $table->foreignId('plan_id')
                ->constrained(config('usage-billing.tables.plan'))
                ->cascadeOnDelete();
            $table->foreignId('module_id')
                ->constrained(config('usage-billing.tables.module'))
                ->cascadeOnDelete();
            $table->unsignedInteger('included_quantity')->nullable();
            $table->decimal('unit_price_ht', 12, 4)->nullable();
            $table->unsignedInteger('hard_ceiling')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->unique(['plan_id', 'module_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('usage-billing.tables.plan_module'));
    }
};

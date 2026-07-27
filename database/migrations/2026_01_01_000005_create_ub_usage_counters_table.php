<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cached usage, one row per subscription/module/period/attribution.
     *
     * `attribution_key` defaults to an empty string rather than null because
     * MySQL permits repeated NULLs inside a unique index, which would let
     * unattributed usage duplicate itself silently.
     */
    public function up(): void
    {
        Schema::create(config('usage-billing.tables.usage_counter'), function (Blueprint $table): void {
            $table->id();
            $table->foreignId('subscription_id')
                ->constrained(config('usage-billing.tables.subscription'))
                ->cascadeOnDelete();
            $table->foreignId('module_id')
                ->constrained(config('usage-billing.tables.module'))
                ->cascadeOnDelete();
            $table->char('period', 7);
            $table->string('attribution_key')->default('');
            $table->string('attribution_type')->nullable();
            $table->unsignedBigInteger('attribution_id')->nullable();
            $table->string('attribution_label')->nullable();
            $table->unsignedInteger('quantity')->default(0);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['subscription_id', 'module_id', 'period', 'attribution_key'],
                'ub_usage_counters_unique'
            );
            $table->index(['subscription_id', 'period'], 'ub_usage_counters_period_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('usage-billing.tables.usage_counter'));
    }
};

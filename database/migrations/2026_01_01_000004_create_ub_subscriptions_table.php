<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('usage-billing.tables.subscription'), function (Blueprint $table): void {
            $table->id();
            $table->morphs('subscriber');
            $table->foreignId('plan_id')
                ->constrained(config('usage-billing.tables.plan'))
                ->restrictOnDelete();
            $table->string('status')->index();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('grace_ends_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['subscriber_type', 'subscriber_id', 'status'], 'ub_subscriptions_subscriber_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('usage-billing.tables.subscription'));
    }
};

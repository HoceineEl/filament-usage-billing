<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('usage-billing.tables.subscription_event'), function (Blueprint $table): void {
            $table->id();
            $table->foreignId('subscription_id')
                ->constrained(config('usage-billing.tables.subscription'))
                ->cascadeOnDelete();
            $table->string('type')->index();
            $table->string('from_value')->nullable();
            $table->string('to_value')->nullable();
            $table->nullableMorphs('actor');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['subscription_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('usage-billing.tables.subscription_event'));
    }
};

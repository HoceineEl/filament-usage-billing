<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Seat pricing: a plan can charge per unit of one module (per client, per
 * user) instead of a flat term price, and a subscription records how many
 * units it has paid for.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table(config('usage-billing.tables.plan'), function (Blueprint $table): void {
            $table->string('seat_module')->nullable()->after('price_ht');
            $table->decimal('seat_price_ht', 12, 2)->nullable()->after('seat_module');
            $table->unsignedInteger('min_seats')->nullable()->after('seat_price_ht');
        });

        Schema::table(config('usage-billing.tables.subscription'), function (Blueprint $table): void {
            $table->unsignedInteger('seats')->nullable()->after('status');
            $table->unsignedInteger('renewal_seats')->nullable()->after('seats');
        });
    }

    public function down(): void
    {
        Schema::table(config('usage-billing.tables.subscription'), function (Blueprint $table): void {
            $table->dropColumn(['seats', 'renewal_seats']);
        });

        Schema::table(config('usage-billing.tables.plan'), function (Blueprint $table): void {
            $table->dropColumn(['seat_module', 'seat_price_ht', 'min_seats']);
        });
    }
};

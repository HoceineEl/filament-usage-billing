<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Subscriptions are sold as prepaid terms rather than billed month by month,
 * so a plan's price is the price of one whole term.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table(config('usage-billing.tables.plan'), function (Blueprint $table): void {
            $table->unsignedSmallInteger('term_months')->default(12)->after('price_ht');
            $table->unsignedSmallInteger('renewal_notice_days')->default(30)->after('grace_days');
        });
    }

    public function down(): void
    {
        Schema::table(config('usage-billing.tables.plan'), function (Blueprint $table): void {
            $table->dropColumn(['term_months', 'renewal_notice_days']);
        });
    }
};

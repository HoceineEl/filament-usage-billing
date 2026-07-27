<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One invoice per subscription per period was the right guard while billing ran
 * monthly in arrears. Selling prepaid terms breaks it: a cabinet can be issued
 * a renewal and a mid-term upgrade in the same month, and both are legitimate.
 *
 * Duplicate-billing protection moved to `TermBiller`, which returns the
 * outstanding term invoice instead of writing a second one.
 */
return new class extends Migration
{
    public function up(): void
    {
        // The plain index is created first on purpose: MySQL leans on the
        // unique one to back the subscription_id foreign key and refuses to
        // drop it while it is the only candidate.
        Schema::table(config('usage-billing.tables.invoice'), function (Blueprint $table): void {
            $table->index(['subscription_id', 'period'], 'ub_invoices_subscription_period_index');
        });

        Schema::table(config('usage-billing.tables.invoice'), function (Blueprint $table): void {
            $table->dropUnique(['subscription_id', 'period']);
        });
    }

    public function down(): void
    {
        Schema::table(config('usage-billing.tables.invoice'), function (Blueprint $table): void {
            $table->unique(['subscription_id', 'period']);
        });

        Schema::table(config('usage-billing.tables.invoice'), function (Blueprint $table): void {
            $table->dropIndex('ub_invoices_subscription_period_index');
        });
    }
};

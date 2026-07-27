<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Moroccan factures require an unbroken yearly sequence, so the counter is
     * a row taken under `lockForUpdate` and a number is only ever handed out
     * when an invoice is actually issued.
     */
    public function up(): void
    {
        Schema::create(config('usage-billing.tables.invoice_sequence'), function (Blueprint $table): void {
            $table->id();
            $table->string('prefix');
            $table->unsignedSmallInteger('fiscal_year');
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();

            $table->unique(['prefix', 'fiscal_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('usage-billing.tables.invoice_sequence'));
    }
};

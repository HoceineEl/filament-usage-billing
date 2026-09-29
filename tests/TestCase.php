<?php

declare(strict_types=1);

namespace HoceineEl\UsageBilling\Tests;

use HoceineEl\UsageBilling\Tests\Fixtures\DocumentsModule;
use HoceineEl\UsageBilling\Tests\Fixtures\MessagesModule;
use HoceineEl\UsageBilling\Tests\Fixtures\SeatsModule;
use HoceineEl\UsageBilling\UsageBillingServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createFixtureTables();
        $this->artisan('usage-billing:sync-modules')->run();
    }

    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [UsageBillingServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('usage-billing.modules', [
            DocumentsModule::class,
            SeatsModule::class,
            MessagesModule::class,
        ]);
        $app['config']->set('usage-billing.seller.name', 'Platform SARL');
        $app['config']->set('usage-billing.cache.ttl', 0);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    private function createFixtureTables(): void
    {
        Schema::create('cabinets', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('ice')->nullable();
            $table->string('identifiant_fiscal')->nullable();
            $table->string('address')->nullable();
            $table->timestamps();
        });

        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cabinet_id');
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cabinet_id');
            $table->foreignId('customer_id')->nullable();
            $table->boolean('failed')->default(false);
            $table->timestamps();
        });

        Schema::create('messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cabinet_id');
            $table->timestamp('sent_at');
        });
    }
}

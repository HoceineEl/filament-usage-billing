<?php

declare(strict_types=1);

namespace HoceineEl\UsageBilling\Tests;

use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use Filament\Actions\ActionsServiceProvider;
use Filament\FilamentServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Infolists\InfolistsServiceProvider;
use Filament\Notifications\NotificationsServiceProvider;
use Filament\Schemas\SchemasServiceProvider;
use Filament\Support\SupportServiceProvider;
use Filament\Tables\TablesServiceProvider;
use Filament\Widgets\WidgetsServiceProvider;
use HoceineEl\UsageBilling\Tests\Fixtures\AdminPanelProvider;
use HoceineEl\UsageBilling\Tests\Fixtures\DocumentsModule;
use HoceineEl\UsageBilling\Tests\Fixtures\MessagesModule;
use HoceineEl\UsageBilling\Tests\Fixtures\SeatsModule;
use HoceineEl\UsageBilling\Tests\Fixtures\TestPanelProvider;
use HoceineEl\UsageBilling\Tests\Fixtures\User;
use HoceineEl\UsageBilling\UsageBillingServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use RyanChandler\BladeCaptureDirective\BladeCaptureDirectiveServiceProvider;

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
        return [
            LivewireServiceProvider::class,
            BladeCaptureDirectiveServiceProvider::class,
            BladeIconsServiceProvider::class,
            BladeHeroiconsServiceProvider::class,
            SupportServiceProvider::class,
            SchemasServiceProvider::class,
            ActionsServiceProvider::class,
            FormsServiceProvider::class,
            InfolistsServiceProvider::class,
            NotificationsServiceProvider::class,
            TablesServiceProvider::class,
            WidgetsServiceProvider::class,
            FilamentServiceProvider::class,
            UsageBillingServiceProvider::class,
            AdminPanelProvider::class,
            TestPanelProvider::class,
        ];
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
        $app['config']->set('auth.providers.users.model', User::class);
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

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cabinet_id')->nullable();
            $table->string('name');
            $table->string('email');
            $table->string('password');
            $table->boolean('is_owner')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cabinet_id');
            $table->timestamp('sent_at');
        });
    }
}

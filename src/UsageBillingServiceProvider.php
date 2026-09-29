<?php

declare(strict_types=1);

namespace HoceineEl\UsageBilling;

use HoceineEl\UsageBilling\Commands\ClosePeriodCommand;
use HoceineEl\UsageBilling\Commands\EndTrialsCommand;
use HoceineEl\UsageBilling\Commands\ExpireSubscriptionsCommand;
use HoceineEl\UsageBilling\Commands\IssueRenewalsCommand;
use HoceineEl\UsageBilling\Commands\MarkOverdueCommand;
use HoceineEl\UsageBilling\Commands\SyncModulesCommand;
use HoceineEl\UsageBilling\Contracts\InvoiceRenderer;
use HoceineEl\UsageBilling\Renderers\HtmlInvoiceRenderer;
use HoceineEl\UsageBilling\Services\InvoiceBuilder;
use HoceineEl\UsageBilling\Services\InvoiceNumberGenerator;
use HoceineEl\UsageBilling\Services\ModuleGate;
use HoceineEl\UsageBilling\Services\ModuleRegistry;
use HoceineEl\UsageBilling\Services\SubscriptionManager;
use HoceineEl\UsageBilling\Services\TermBiller;
use HoceineEl\UsageBilling\Services\UsageReader;
use HoceineEl\UsageBilling\Services\UsageRecorder;
use HoceineEl\UsageBilling\Services\UsageSynchronizer;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class UsageBillingServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('usage-billing')
            ->hasConfigFile('usage-billing')
            ->hasViews('usage-billing')
            ->hasTranslations()
            ->hasCommands([
                SyncModulesCommand::class,
                IssueRenewalsCommand::class,
                EndTrialsCommand::class,
                ExpireSubscriptionsCommand::class,
                ClosePeriodCommand::class,
                MarkOverdueCommand::class,
            ]);
    }

    public function packageBooted(): void
    {
        // Loaded rather than declared through hasMigrations(), which expects
        // timestamp-less stub filenames. Publishing stays available for apps
        // that want to own the schema.
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'usage-billing-migrations');
    }

    public function packageRegistered(): void
    {
        // The registry memoises resolved module classes, so it must be a
        // singleton or every gate check re-instantiates the whole list.
        $this->app->singleton(ModuleRegistry::class);
        $this->app->singleton(UsageReader::class);
        $this->app->singleton(ModuleGate::class);
        $this->app->singleton(UsageRecorder::class);
        $this->app->singleton(UsageSynchronizer::class);
        $this->app->singleton(InvoiceNumberGenerator::class);
        $this->app->singleton(InvoiceBuilder::class);
        $this->app->singleton(SubscriptionManager::class);
        $this->app->singleton(TermBiller::class);

        $this->app->bindIf(InvoiceRenderer::class, HtmlInvoiceRenderer::class);
    }
}

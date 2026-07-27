<?php

declare(strict_types=1);

namespace HoceineEl\UsageBilling\Commands;

use HoceineEl\UsageBilling\Services\ModuleRegistry;
use Illuminate\Console\Command;

class SyncModulesCommand extends Command
{
    protected $signature = 'usage-billing:sync-modules';

    protected $description = 'Reconcile the module registry table with the configured metered module classes';

    public function handle(ModuleRegistry $registry): int
    {
        $result = $registry->sync();

        $this->components->info(sprintf(
            '%d created, %d updated, %d deactivated.',
            $result['created'],
            $result['updated'],
            $result['deactivated'],
        ));

        if ($result['deactivated'] > 0) {
            $this->components->warn(
                'Deactivated modules keep their rows so existing plan pricing and issued invoices stay readable.'
            );
        }

        return self::SUCCESS;
    }
}

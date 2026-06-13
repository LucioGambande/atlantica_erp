<?php

namespace App\Console\Commands;

use App\Jobs\SyncHubSpotCompaniesJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncHubSpotCompaniesCommand extends Command
{
    protected $signature = 'hubspot:sync-companies {--full : Run a full sync} {--incremental : Run an incremental sync}';

    protected $description = 'Sync HubSpot companies into Laravel customers.';

    public function handle(): int
    {
        $full = (bool) $this->option('full');
        $incremental = (bool) $this->option('incremental');

        if ($full && $incremental) {
            $this->error('Use either --full or --incremental, not both.');

            return self::FAILURE;
        }

        $fullMode = $full;

        SyncHubSpotCompaniesJob::dispatch($fullMode);

        $mode = $fullMode ? 'full' : 'incremental';
        $this->info("HubSpot {$mode} sync dispatched.");

        Log::channel('hubspot')->info('HubSpot sync command dispatched a job.', [
            'mode' => $mode,
        ]);

        return self::SUCCESS;
    }
}

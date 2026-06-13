<?php

namespace App\Jobs;

use App\Services\HubSpotCompanySyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncSingleCompanyByIdJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /**
     * @var array<int, int>
     */
    public array $backoff = [30, 120, 300, 900];

    public function __construct(
        public string $hubspotCompanyId,
    ) {
    }

    public function handle(HubSpotCompanySyncService $syncService): void
    {
        Log::channel('hubspot')->info('SyncSingleCompanyByIdJob started.', [
            'hubspot_company_id' => $this->hubspotCompanyId,
        ]);

        $result = $syncService->syncByHubSpotCompanyId($this->hubspotCompanyId);

        if ($result['failed'] > 0) {
            throw new \RuntimeException("Failed to sync HubSpot company {$this->hubspotCompanyId}.");
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::channel('hubspot')->error('SyncSingleCompanyByIdJob failed.', [
            'hubspot_company_id' => $this->hubspotCompanyId,
            'error' => $exception->getMessage(),
        ]);
    }
}

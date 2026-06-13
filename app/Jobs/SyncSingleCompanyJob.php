<?php

namespace App\Jobs;

use App\Services\HubSpotCompanySyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncSingleCompanyJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /**
     * @var array<int, int>
     */
    public array $backoff = [30, 120, 300, 900];

    /**
     * @param array<string, mixed> $companyData
     */
    public function __construct(
        public array $companyData,
    ) {
    }

    public function handle(HubSpotCompanySyncService $syncService): void
    {
        $result = $syncService->upsertFromHubSpot($this->companyData);

        if ($result['failed'] > 0) {
            throw new \RuntimeException('Single HubSpot company sync failed.');
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::channel('hubspot')->error('SyncSingleCompanyJob failed.', [
            'hubspot_company_id' => $this->companyData['id'] ?? null,
            'payload' => $this->companyData,
            'error' => $exception->getMessage(),
        ]);
    }
}

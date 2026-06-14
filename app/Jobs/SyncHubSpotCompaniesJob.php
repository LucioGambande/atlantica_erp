<?php

namespace App\Jobs;

use App\Integrations\HubSpot\HubSpotClient;
use App\Integrations\HubSpot\HubSpotCompanyService;
use App\Services\HubSpotCompanySyncService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncHubSpotCompaniesJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [60, 180, 600];

    public function __construct(
        public bool $full = false,
    ) {
    }

    public function handle(
        HubSpotCompanyService $hubSpotCompanyService,
        HubSpotCompanySyncService $syncService,
    ): void {
        Log::channel('hubspot')->info('SyncHubSpotCompaniesJob started.', [
            'mode' => $this->full ? 'full' : 'incremental',
        ]);

        $after = null;
        $dispatched = 0;
        $lastSyncAt = $syncService->getLastIncrementalSyncAt();
        $updatedAfterMs = $lastSyncAt?->valueOf();

        do {
            $page = ($this->full || $updatedAfterMs === null)
                ? $hubSpotCompanyService->getPage($after)
                : $hubSpotCompanyService->getIncrementalPage((string) $updatedAfterMs, $after);

            foreach ($page['results'] as $company) {
                SyncSingleCompanyJob::dispatch($company);
                $dispatched++;
            }

            $after = $page['next_after'];
        } while ($after !== null);

        $syncService->updateLastIncrementalSyncAt(CarbonImmutable::now());

        Log::channel('hubspot')->info('HubSpot company sync dispatch completed.', [
            'mode' => $this->full ? 'full' : 'incremental',
            'dispatched' => $dispatched,
        ]);
    }

    public function failed(Throwable $exception): void
    {
        $message = $exception instanceof RequestException
            ? HubSpotClient::explainHttpFailure($exception)
            : $exception->getMessage();

        Log::channel('hubspot')->error('SyncHubSpotCompaniesJob failed.', [
            'mode' => $this->full ? 'full' : 'incremental',
            'error' => $message,
        ]);
    }
}

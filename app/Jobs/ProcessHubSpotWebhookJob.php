<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessHubSpotWebhookJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @param array<int, array<string, mixed>> $events
     */
    public function __construct(
        public array $events,
    ) {
    }

    public function handle(): void
    {
        $allowedTypes = config('hubspot.webhook.subscription_types', []);
        $processedIds = [];

        foreach ($this->events as $event) {
            $subscriptionType = $event['subscriptionType'] ?? null;
            $objectId = $event['objectId'] ?? null;

            if (! is_string($subscriptionType) || ! in_array($subscriptionType, $allowedTypes, true)) {
                continue;
            }

            if (! is_numeric($objectId)) {
                Log::channel('hubspot')->warning('HubSpot webhook event missing objectId.', [
                    'event' => $event,
                ]);

                continue;
            }

            $hubspotCompanyId = (string) $objectId;

            if (isset($processedIds[$hubspotCompanyId])) {
                continue;
            }

            $processedIds[$hubspotCompanyId] = true;

            Log::channel('hubspot')->info('HubSpot webhook event queued for sync.', [
                'hubspot_company_id' => $hubspotCompanyId,
                'subscription_type' => $subscriptionType,
            ]);

            SyncSingleCompanyByIdJob::dispatch($hubspotCompanyId);
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::channel('hubspot')->error('ProcessHubSpotWebhookJob failed.', [
            'events_count' => count($this->events),
            'error' => $exception->getMessage(),
        ]);
    }
}

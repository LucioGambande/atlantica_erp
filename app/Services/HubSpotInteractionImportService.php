<?php

namespace App\Services;

use App\Integrations\HubSpot\HubSpotClient;
use App\Integrations\HubSpot\HubSpotEngagementMapper;
use App\Models\Customer;
use App\Models\CustomerInteraction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

class HubSpotInteractionImportService
{
    public function __construct(
        protected HubSpotClient $client,
        protected HubSpotEngagementMapper $mapper,
    ) {}

    /**
     * @param  list<string>  $engagementTypes
     * @return array{fetched:int,imported:int,skipped:int}
     */
    public function importForCustomer(Customer $customer, array $engagementTypes, ?CarbonImmutable $since, bool $dryRun): array
    {
        $stats = ['fetched' => 0, 'imported' => 0, 'skipped' => 0];

        if (! is_string($customer->hubspot_company_id) || $customer->hubspot_company_id === '') {
            return $stats;
        }

        $ownerMap = $this->ownerIdToUserId();

        foreach ($engagementTypes as $engagementType) {
            $ids = $this->client->getAssociatedObjectIds('companies', $customer->hubspot_company_id, $engagementType);

            if ($ids === []) {
                continue;
            }

            $engagements = $this->client->batchReadObjects(
                $engagementType,
                $ids,
                HubSpotEngagementMapper::propertiesFor($engagementType),
            );

            foreach ($engagements as $engagement) {
                $stats['fetched']++;

                $mapped = $this->mapper->map($engagementType, $engagement, $customer->id, $ownerMap);

                if ($mapped === null) {
                    $stats['skipped']++;

                    continue;
                }

                if ($since !== null && $mapped['happened_at']->lt($since)) {
                    $stats['skipped']++;

                    continue;
                }

                if (! $dryRun) {
                    CustomerInteraction::query()->updateOrCreate(
                        ['external_id' => $mapped['external_id']],
                        $mapped,
                    );
                }

                $stats['imported']++;
            }
        }

        return $stats;
    }

    /**
     * @return array<string, int>
     */
    protected function ownerIdToUserId(): array
    {
        return once(function (): array {
            $usersByEmail = User::query()->pluck('id', 'email')->all();
            $map = [];

            try {
                $owners = $this->client->getOwners();
            } catch (\Throwable $exception) {
                Log::channel('hubspot')->warning('No se pudieron obtener los owners de HubSpot; se importa sin mapear usuario.', [
                    'error' => $exception->getMessage(),
                ]);

                return $map;
            }

            foreach ($owners as $owner) {
                $email = $owner['email'] ?? null;
                $ownerId = $owner['id'] ?? null;

                if (is_string($email) && $ownerId !== null && isset($usersByEmail[$email])) {
                    $map[(string) $ownerId] = $usersByEmail[$email];
                }
            }

            return $map;
        });
    }
}

<?php

namespace App\Services;

use App\Integrations\HubSpot\HubSpotCompanyService;
use App\Integrations\HubSpot\HubSpotMapper;
use App\Models\Customer;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class HubSpotCompanySyncService
{
    public function __construct(
        protected HubSpotCompanyService $hubSpotCompanyService,
        protected HubSpotMapper $mapper,
    ) {
    }

    /**
     * @return array{processed:int,created:int,updated:int,failed:int}
     */
    public function syncAllCompanies(): array
    {
        $after = null;
        $stats = $this->emptyStats();

        do {
            $page = $this->hubSpotCompanyService->getPage($after);

            foreach ($page['results'] as $company) {
                $stats = $this->mergeStats($stats, $this->safeUpsert($company));
            }

            $after = $page['next_after'];
        } while ($after !== null);

        $this->updateLastIncrementalSyncAt(CarbonImmutable::now());

        return $stats;
    }

    /**
     * @return array{processed:int,created:int,updated:int,failed:int}
     */
    public function syncIncremental(): array
    {
        $after = null;
        $stats = $this->emptyStats();
        $lastSyncAt = $this->getLastIncrementalSyncAt();
        $updatedAfterMs = $lastSyncAt?->valueOf();

        do {
            $page = $updatedAfterMs !== null
                ? $this->hubSpotCompanyService->getIncrementalPage((string) $updatedAfterMs, $after)
                : $this->hubSpotCompanyService->getPage($after);

            foreach ($page['results'] as $company) {
                $stats = $this->mergeStats($stats, $this->safeUpsert($company));
            }

            $after = $page['next_after'];
        } while ($after !== null);

        $this->updateLastIncrementalSyncAt(CarbonImmutable::now());

        return $stats;
    }

    /**
     * @param array<string, mixed> $companyData
     * @return array{processed:int,created:int,updated:int,failed:int}
     */
    public function upsertFromHubSpot(array $companyData): array
    {
        $mapped = $this->mapper->mapCompanyToCustomer($companyData);

        $hubspotCompanyId = $mapped['hubspot_company_id'] ?? null;

        if (! is_string($hubspotCompanyId) || $hubspotCompanyId === '') {
            throw new \InvalidArgumentException('HubSpot company id is missing.');
        }

        $customer = Customer::query()
            ->where('hubspot_company_id', $hubspotCompanyId)
            ->first();

        if ($customer === null && ! empty($mapped['website'])) {
            $customer = Customer::query()
                ->where('website', $mapped['website'])
                ->whereNull('hubspot_company_id')
                ->first();
        }

        if ($customer === null) {
            Customer::query()->create($this->buildCreatePayload($mapped));

            return ['processed' => 1, 'created' => 1, 'updated' => 0, 'failed' => 0];
        }

        $customer->fill($this->buildUpdatePayload($customer->toArray(), $mapped));
        $customer->save();

        return ['processed' => 1, 'created' => 0, 'updated' => 1, 'failed' => 0];
    }

    public function getLastIncrementalSyncAt(): ?CarbonImmutable
    {
        $raw = Cache::get($this->incrementalCacheKey());

        return is_string($raw) && $raw !== '' ? CarbonImmutable::parse($raw) : null;
    }

    public function updateLastIncrementalSyncAt(CarbonImmutable $date): void
    {
        Cache::forever($this->incrementalCacheKey(), $date->toIso8601String());
    }

    /**
     * @param array<string, mixed> $companyData
     * @return array{processed:int,created:int,updated:int,failed:int}
     */
    protected function safeUpsert(array $companyData): array
    {
        try {
            return $this->upsertFromHubSpot($companyData);
        } catch (Throwable $exception) {
            Log::channel('hubspot')->error('Failed to sync a HubSpot company.', [
                'hubspot_company_id' => $companyData['id'] ?? null,
                'payload' => $companyData,
                'error' => $exception->getMessage(),
            ]);

            return ['processed' => 1, 'created' => 0, 'updated' => 0, 'failed' => 1];
        }
    }

    /**
     * @param array<string, mixed> $mapped
     * @return array<string, mixed>
     */
    protected function buildCreatePayload(array $mapped): array
    {
        return [
            'name' => $mapped['name'] ?? 'Empresa sin nombre',
            'phone' => $mapped['phone'] ?? null,
            'website' => $mapped['website'] ?? null,
            'address' => $mapped['address'] ?? null,
            'city' => $mapped['city'] ?? null,
            'postal_code' => $mapped['postal_code'] ?? null,
            'country' => $mapped['country'] ?? null,
            'customer_type' => 'horeca',
            'credit_limit' => 0,
            'hubspot_company_id' => $mapped['hubspot_company_id'],
            'hubspot_last_modified_at' => $mapped['hubspot_last_modified_at'] ?? null,
            'last_synced_at' => now(),
        ];
    }

    /**
     * @param array<string, mixed> $existing
     * @param array<string, mixed> $mapped
     * @return array<string, mixed>
     */
    protected function buildUpdatePayload(array $existing, array $mapped): array
    {
        $payload = [
            'hubspot_company_id' => $mapped['hubspot_company_id'],
            'hubspot_last_modified_at' => $mapped['hubspot_last_modified_at'] ?? null,
            'last_synced_at' => now(),
        ];

        foreach (['name', 'phone', 'website', 'address', 'city', 'postal_code', 'country'] as $field) {
            // Placeholder for future bidirectional support.
            // Here we can preserve manually edited fields using metadata/audits.
            if ($this->shouldPreserveManualValue($field, $existing[$field] ?? null, $mapped[$field] ?? null)) {
                continue;
            }

            $payload[$field] = $mapped[$field] ?? null;
        }

        return $payload;
    }

    /**
     * @param mixed $existingValue
     * @param mixed $incomingValue
     */
    protected function shouldPreserveManualValue(string $field, mixed $existingValue, mixed $incomingValue): bool
    {
        // Future-safe hook: keep here for bidirectional logic.
        // For now we do not block updates, only centralize decision point.
        return false;
    }

    /**
     * @return array{processed:int,created:int,updated:int,failed:int}
     */
    protected function emptyStats(): array
    {
        return ['processed' => 0, 'created' => 0, 'updated' => 0, 'failed' => 0];
    }

    /**
     * @param array{processed:int,created:int,updated:int,failed:int} $left
     * @param array{processed:int,created:int,updated:int,failed:int} $right
     * @return array{processed:int,created:int,updated:int,failed:int}
     */
    protected function mergeStats(array $left, array $right): array
    {
        return [
            'processed' => $left['processed'] + $right['processed'],
            'created' => $left['created'] + $right['created'],
            'updated' => $left['updated'] + $right['updated'],
            'failed' => $left['failed'] + $right['failed'],
        ];
    }

    protected function incrementalCacheKey(): string
    {
        return (string) config('hubspot.incremental_cache_key', 'hubspot.companies.last_incremental_sync_at');
    }
}

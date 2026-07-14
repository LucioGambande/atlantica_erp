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
     * @return array{processed:int,created:int,updated:int,failed:int}
     */
    public function syncByHubSpotCompanyId(string $hubspotCompanyId): array
    {
        $company = $this->hubSpotCompanyService->getCompanyById($hubspotCompanyId);

        return $this->upsertFromHubSpot($company);
    }

    /**
     * @throws \InvalidArgumentException
     * @return array{processed:int,created:int,updated:int,failed:int}
     */
    public function syncCustomer(Customer $customer): array
    {
        $hubspotCompanyId = $customer->hubspot_company_id;

        if (! is_string($hubspotCompanyId) || $hubspotCompanyId === '') {
            throw new \InvalidArgumentException('Este cliente no está vinculado a HubSpot.');
        }

        return $this->syncByHubSpotCompanyId($hubspotCompanyId);
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

            Log::channel('hubspot')->info('HubSpot company created as customer.', [
                'hubspot_company_id' => $hubspotCompanyId,
            ]);

            return ['processed' => 1, 'created' => 1, 'updated' => 0, 'failed' => 0];
        }

        $customer->fill($this->buildUpdatePayload($customer->toArray(), $mapped));
        $customer->save();

        Log::channel('hubspot')->info('HubSpot company updated customer.', [
            'hubspot_company_id' => $hubspotCompanyId,
            'customer_id' => $customer->id,
        ]);

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
        $payload = [
            'name' => $mapped['name'] ?? 'Empresa sin nombre',
            'customer_type' => 'horeca',
            'credit_limit' => 0,
            'hubspot_company_id' => $mapped['hubspot_company_id'],
            'hubspot_properties' => $mapped['hubspot_properties'] ?? null,
            'last_synced_at' => now(),
        ];

        foreach ($this->mapper->mappedCustomerColumns() as $column) {
            if (array_key_exists($column, $mapped)) {
                $payload[$column] = $mapped[$column];
            }
        }

        return $payload;
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
            'hubspot_properties' => $mapped['hubspot_properties'] ?? null,
            'last_synced_at' => now(),
        ];

        foreach ($this->mapper->mappedCustomerColumns() as $column) {
            if ($this->shouldPreserveManualValue($column, $existing[$column] ?? null, $mapped[$column] ?? null)) {
                continue;
            }

            $payload[$column] = $mapped[$column] ?? null;
        }

        return $payload;
    }

    /**
     * @param mixed $existingValue
     * @param mixed $incomingValue
     */
    protected function shouldPreserveManualValue(string $field, mixed $existingValue, mixed $incomingValue): bool
    {
        return in_array($field, config('hubspot.erp_only_fields', []), true);
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

<?php

namespace App\Integrations\HubSpot;

class HubSpotCompanyService
{
    public function __construct(
        protected HubSpotClient $client,
    ) {
    }

    /**
     * @return list<string>
     */
    public function syncedProperties(): array
    {
        return HubSpotCompanyPropertyList::syncedProperties();
    }

    /**
     * @return array{results: array<int, array<string, mixed>>, next_after: string|null}
     */
    public function getPage(?string $after = null): array
    {
        $response = $this->client->getCompanies([
            'after' => $after,
            'limit' => config('hubspot.page_limit', 100),
        ]);

        return $this->extractPageData($response);
    }

    /**
     * @return array{results: array<int, array<string, mixed>>, next_after: string|null}
     */
    public function getIncrementalPage(string $updatedAfterMilliseconds, ?string $after = null): array
    {
        $response = $this->client->getCompanies([
            'updated_after' => $updatedAfterMilliseconds,
            'after' => $after,
            'limit' => config('hubspot.page_limit', 100),
        ]);

        return $this->extractPageData($response);
    }

    /**
     * @return array<string, mixed>
     */
    public function getCompanyById(string $id): array
    {
        return $this->client->getCompanyById($id);
    }

    /**
     * @param array<string, mixed> $response
     * @return array{results: array<int, array<string, mixed>>, next_after: string|null}
     */
    protected function extractPageData(array $response): array
    {
        /** @var array<int, array<string, mixed>> $results */
        $results = $response['results'] ?? [];
        $nextAfter = $response['paging']['next']['after'] ?? null;

        return [
            'results' => $results,
            'next_after' => is_scalar($nextAfter) ? (string) $nextAfter : null,
        ];
    }
}

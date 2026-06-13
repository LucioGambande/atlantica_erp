<?php

namespace App\Integrations\HubSpot;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class HubSpotClient
{
    /**
     * @return array<string, mixed>
     */
    public function getCompanies(array $params = []): array
    {
        if (isset($params['updated_after'])) {
            return $this->searchCompaniesByUpdatedAt($params);
        }

        $response = $this->request()
            ->get('/crm/v3/objects/companies', $this->buildListParams($params))
            ->throw();

        return $response->json();
    }

    /**
     * @return array<string, mixed>
     */
    public function getCompanyById(string $id): array
    {
        $response = $this->request()
            ->get("/crm/v3/objects/companies/{$id}", [
                'properties' => implode(',', HubSpotCompanyPropertyList::syncedProperties()),
            ])
            ->throw();

        return $response->json();
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    protected function searchCompaniesByUpdatedAt(array $params): array
    {
        $updatedAfter = $params['updated_after'] ?? null;

        if ($updatedAfter === null) {
            throw new RuntimeException('Parameter "updated_after" is required for incremental sync.');
        }

        $body = array_filter([
            'limit' => $params['limit'] ?? config('hubspot.page_limit', 100),
            'after' => $params['after'] ?? null,
            'sorts' => ['hs_lastmodifieddate'],
            'filterGroups' => [
                [
                    'filters' => [
                        [
                            'propertyName' => 'hs_lastmodifieddate',
                            'operator' => 'GT',
                            'value' => (string) $updatedAfter,
                        ],
                    ],
                ],
            ],
            'properties' => HubSpotCompanyPropertyList::syncedProperties(),
        ], static fn (mixed $value): bool => $value !== null);

        $response = $this->request()
            ->post('/crm/v3/objects/companies/search', $body)
            ->throw();

        return $response->json();
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    protected function buildListParams(array $params): array
    {
        return array_filter([
            'limit' => $params['limit'] ?? config('hubspot.page_limit', 100),
            'after' => $params['after'] ?? null,
            'properties' => implode(',', HubSpotCompanyPropertyList::syncedProperties()),
        ], static fn (mixed $value): bool => $value !== null);
    }

    protected function request(): PendingRequest
    {
        $token = (string) config('hubspot.access_token');

        if ($token === '') {
            throw new RuntimeException('HUBSPOT_ACCESS_TOKEN is not configured.');
        }

        return Http::baseUrl((string) config('hubspot.base_url'))
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('hubspot.timeout_seconds', 15))
            ->retry(
                4,
                fn (int $attempt): int => $attempt * 500,
                fn (\Exception $exception): bool => $this->shouldRetry($exception),
            )
            ->withToken($token);
    }

    protected function shouldRetry(\Exception $exception): bool
    {
        if (! $exception instanceof RequestException || $exception->response === null) {
            return true;
        }

        return in_array($exception->response->status(), [429, 500, 502, 503, 504], true);
    }
}

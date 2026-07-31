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
     * @param  array<string, mixed>  $properties
     * @return array<string, mixed>
     */
    public function updateCompany(string $id, array $properties): array
    {
        $response = $this->request()
            ->patch("/crm/v3/objects/companies/{$id}", [
                'properties' => $properties,
            ])
            ->throw();

        return $response->json();
    }

    /**
     * Returns the ids of every object of $toObjectType associated with $fromObjectId.
     *
     * @return list<string>
     */
    public function getAssociatedObjectIds(string $fromObjectType, string $fromObjectId, string $toObjectType): array
    {
        $ids = [];
        $after = null;

        do {
            $response = $this->request()
                ->get("/crm/v4/objects/{$fromObjectType}/{$fromObjectId}/associations/{$toObjectType}", array_filter([
                    'limit' => 500,
                    'after' => $after,
                ]))
                ->throw()
                ->json();

            foreach ($response['results'] ?? [] as $result) {
                if (isset($result['toObjectId'])) {
                    $ids[] = (string) $result['toObjectId'];
                }
            }

            $after = $response['paging']['next']['after'] ?? null;
        } while ($after !== null);

        return $ids;
    }

    /**
     * Batch-reads objects by id, chunking in groups of 100 (HubSpot's batch limit).
     *
     * @param  list<string>  $ids
     * @param  list<string>  $properties
     * @return array<int, array<string, mixed>>
     */
    public function batchReadObjects(string $objectType, array $ids, array $properties): array
    {
        $objects = [];

        foreach (array_chunk($ids, 100) as $chunk) {
            $response = $this->request()
                ->post("/crm/v3/objects/{$objectType}/batch/read", [
                    'properties' => $properties,
                    'inputs' => array_map(static fn (string $id): array => ['id' => $id], $chunk),
                ])
                ->throw()
                ->json();

            foreach ($response['results'] ?? [] as $result) {
                $objects[] = $result;
            }
        }

        return $objects;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getOwners(): array
    {
        $owners = [];
        $after = null;

        do {
            $response = $this->request()
                ->get('/crm/v3/owners', array_filter([
                    'limit' => 100,
                    'after' => $after,
                ]))
                ->throw()
                ->json();

            foreach ($response['results'] ?? [] as $owner) {
                $owners[] = $owner;
            }

            $after = $response['paging']['next']['after'] ?? null;
        } while ($after !== null);

        return $owners;
    }

    /**
     * @param  array<string, mixed>  $params
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
     * @param  array<string, mixed>  $params
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
        $token = trim((string) config('hubspot.access_token'));

        if ($token === '') {
            throw new RuntimeException(
                HubSpotClient::explainTokenConfiguration('') ?? 'HUBSPOT_ACCESS_TOKEN is not configured.',
            );
        }

        if (! str_starts_with($token, 'pat-')) {
            throw new RuntimeException(
                HubSpotClient::explainTokenConfiguration($token)
                    ?? 'HUBSPOT_ACCESS_TOKEN does not look like a Private App token.',
            );
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

    /**
     * @throws RuntimeException
     */
    public static function explainHttpFailure(RequestException $exception): string
    {
        $status = $exception->response?->status();

        if ($status === 401) {
            return 'HubSpot rechazó el token (401). Usá un access token de Private App completo (pat-eu1-... o pat-na1-...) '
                .'con el scope crm.objects.companies.read. En Laravel Cloud: guardá la variable, redeployá y volvé a intentar.';
        }

        if ($status === 403) {
            return 'HubSpot denegó el acceso (403). Verificá que la Private App tenga los scopes '
                .'crm.objects.companies.read y, para operaciones de escritura, crm.objects.companies.write.';
        }

        return $exception->getMessage();
    }

    public static function explainTokenConfiguration(?string $token = null): ?string
    {
        $token = trim((string) ($token ?? config('hubspot.access_token')));

        if ($token === '') {
            return 'HUBSPOT_ACCESS_TOKEN no está configurado. En Laravel Cloud agregalo en Variables de entorno y redeployá la app.';
        }

        if (! str_starts_with($token, 'pat-')) {
            $hint = preg_match('/^(eu1|na1)-/', $token)
                ? 'Parece una Developer API key (eu1-/na1-), no un token de Private App. '
                : '';

            return $hint
                .'El token debe empezar con pat-eu1- o pat-na1-. '
                .'HubSpot → Settings → Integrations → Private Apps → [tu app] → Auth → Show token.';
        }

        return null;
    }
}

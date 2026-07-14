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
            return 'HubSpot denegó el acceso (403). Verificá que la Private App tenga el scope crm.objects.companies.read.';
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

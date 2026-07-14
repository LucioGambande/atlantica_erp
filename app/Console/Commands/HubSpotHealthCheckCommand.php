<?php

namespace App\Console\Commands;

use App\Integrations\HubSpot\HubSpotClient;
use Illuminate\Console\Command;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class HubSpotHealthCheckCommand extends Command
{
    protected $signature = 'hubspot:health-check';

    protected $description = 'Verify HubSpot access token and companies API access.';

    public function handle(): int
    {
        $token = trim((string) config('hubspot.access_token'));

        if ($token === '') {
            $this->error('HUBSPOT_ACCESS_TOKEN vacío.');
            $this->line(HubSpotClient::explainTokenConfiguration(''));

            return self::FAILURE;
        }

        if ($configError = HubSpotClient::explainTokenConfiguration($token)) {
            $this->error('Formato de token inválido.');
            $this->line($configError);
            $this->line('Prefijo detectado: '.substr($token, 0, min(8, strlen($token))).'... (longitud '.strlen($token).')');

            return self::FAILURE;
        }

        try {
            $response = Http::baseUrl((string) config('hubspot.base_url'))
                ->acceptJson()
                ->timeout(15)
                ->withToken($token)
                ->get('/crm/v3/objects/companies', ['limit' => 1])
                ->throw();

            $total = $response->json('total') ?? count($response->json('results') ?? []);

            $this->info('HubSpot connection OK.');
            $this->line('Companies reachable (sample page returned '.$total.' record(s) in page).');

            return self::SUCCESS;
        } catch (RequestException $exception) {
            $this->error(HubSpotClient::explainHttpFailure($exception));

            return self::FAILURE;
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}

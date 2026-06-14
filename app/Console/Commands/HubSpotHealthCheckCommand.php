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
            $this->error('HUBSPOT_ACCESS_TOKEN is empty in .env');

            return self::FAILURE;
        }

        if (! str_starts_with($token, 'pat-')) {
            $this->error('Token invalid: must start with pat-eu1- or pat-na1- (Private App access token).');
            $this->line('Current value looks like: '.substr($token, 0, 8).'... (length '.strlen($token).')');

            if (preg_match('/^(eu1|na1)-/', $token)) {
                $this->warn('This looks like a Developer API key (eu1-/na1-), not a Private App token.');
                $this->line('Developer API keys cannot be used as Bearer tokens for the CRM API.');
            }

            $this->newLine();
            $this->line('Get the correct token:');
            $this->line('  Settings (⚙) → Integrations → Private Apps → [your app] → Auth → Show token');
            $this->line('  Or: Development → Legacy apps → [your app] → Auth → Show token');
            $this->line('The token is long (~100+ chars) and starts with pat-eu1- or pat-na1-.');

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

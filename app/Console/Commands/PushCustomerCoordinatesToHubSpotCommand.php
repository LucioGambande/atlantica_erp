<?php

namespace App\Console\Commands;

use App\Integrations\HubSpot\HubSpotClient;
use App\Models\Customer;
use App\Services\GeocodingService;
use Illuminate\Console\Command;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;
use Throwable;

class PushCustomerCoordinatesToHubSpotCommand extends Command
{
    protected $signature = 'hubspot:push-coordinates {--dry-run : No escribe en HubSpot, solo muestra lo que geocodificaría}';

    protected $description = 'Geocodifica la dirección de los clientes vinculados a HubSpot y escribe latitude/longitude allá.';

    public function handle(GeocodingService $geocoder, HubSpotClient $client): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $customers = Customer::query()
            ->whereNotNull('hubspot_company_id')
            ->where('hubspot_company_id', '!=', '')
            ->get();

        $this->info("Clientes vinculados a HubSpot: {$customers->count()}");

        $geocoded = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($customers as $customer) {
            $address = collect([$customer->billingAddress(), $customer->city, $customer->country])
                ->filter()
                ->implode(', ');

            if ($address === '') {
                $skipped++;

                continue;
            }

            try {
                $location = $geocoder->geocode($address);
            } catch (Throwable $exception) {
                $failed++;
                Log::channel('hubspot')->error('Geocoding falló para un cliente.', [
                    'customer_id' => $customer->id,
                    'address' => $address,
                    'error' => $exception->getMessage(),
                ]);

                continue;
            }

            if ($location === null) {
                $skipped++;
                $this->warn("Sin resultado de geocoding: {$customer->name} ({$address})");

                continue;
            }

            if ($dryRun) {
                $this->line("{$customer->name}: {$location['lat']}, {$location['lng']}");
                $geocoded++;

                continue;
            }

            try {
                $client->updateCompany($customer->hubspot_company_id, [
                    'latitude' => (string) $location['lat'],
                    'longitude' => (string) $location['lng'],
                ]);
            } catch (RequestException $exception) {
                $failed++;
                Log::channel('hubspot')->error('No se pudo actualizar lat/lng en HubSpot.', [
                    'customer_id' => $customer->id,
                    'hubspot_company_id' => $customer->hubspot_company_id,
                    'error' => HubSpotClient::explainHttpFailure($exception),
                ]);

                continue;
            }

            $geocoded++;
            usleep(200_000);
        }

        $this->info("Geocodificados: {$geocoded} · Sin match: {$skipped} · Fallidos: {$failed}");

        return self::SUCCESS;
    }
}

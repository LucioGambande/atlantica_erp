<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class GeocodingService
{
    /**
     * @return array{lat: float, lng: float}|null
     */
    public function geocode(string $address): ?array
    {
        $apiKey = trim((string) config('services.google_geocoding.api_key'));

        if ($apiKey === '') {
            throw new RuntimeException('GOOGLE_GEOCODING_API_KEY no está configurada.');
        }

        $response = Http::timeout(10)
            ->retry(3, 500)
            ->get('https://maps.googleapis.com/maps/api/geocode/json', [
                'address' => $address,
                'key' => $apiKey,
            ])
            ->throw();

        $data = $response->json();

        if (($data['status'] ?? null) !== 'OK') {
            return null;
        }

        $location = $data['results'][0]['geometry']['location'] ?? null;

        if (! is_array($location) || ! isset($location['lat'], $location['lng'])) {
            return null;
        }

        return [
            'lat' => (float) $location['lat'],
            'lng' => (float) $location['lng'],
        ];
    }
}

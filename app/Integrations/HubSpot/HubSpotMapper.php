<?php

namespace App\Integrations\HubSpot;

use Carbon\CarbonImmutable;

class HubSpotMapper
{
    /**
     * @param array<string, mixed> $company
     * @return array<string, mixed>
     */
    public function mapCompanyToCustomer(array $company): array
    {
        /** @var array<string, mixed> $properties */
        $properties = $company['properties'] ?? [];

        return [
            'hubspot_company_id' => (string) ($company['id'] ?? ''),
            'name' => $this->nullableString($properties['name'] ?? null),
            'phone' => $this->nullableString($properties['phone'] ?? null),
            'website' => $this->nullableString($properties['domain'] ?? null),
            'city' => $this->nullableString($properties['city'] ?? null),
            'address' => $this->nullableString($properties['address'] ?? null),
            'postal_code' => $this->nullableString($properties['zip'] ?? null),
            'country' => $this->nullableString($properties['country'] ?? null),
            'hubspot_last_modified_at' => $this->parseHubSpotDate($properties['hs_lastmodifieddate'] ?? null),
        ];
    }

    /**
     * @param mixed $value
     */
    protected function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $stringValue = trim((string) $value);

        return $stringValue !== '' ? $stringValue : null;
    }

    /**
     * @param mixed $value
     */
    protected function parseHubSpotDate(mixed $value): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        // HubSpot commonly returns hs_lastmodifieddate as unix epoch in milliseconds.
        if (is_numeric($value)) {
            return CarbonImmutable::createFromTimestampMs((int) $value);
        }

        return CarbonImmutable::parse((string) $value);
    }
}

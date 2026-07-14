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

        $mapped = [
            'hubspot_company_id' => (string) ($company['id'] ?? ''),
            'hubspot_properties' => $properties,
            'last_synced_at' => now(),
        ];

        foreach (config('hubspot.company_field_map', []) as $hubspotProperty => $definition) {
            $column = $definition['column'] ?? null;
            $type = $definition['type'] ?? 'string';

            if (! is_string($column) || $column === '') {
                continue;
            }

            $value = $this->castValue($properties[$hubspotProperty] ?? null, $type);

            if ($value !== null) {
                $mapped[$column] = $value;
            } elseif (! array_key_exists($column, $mapped)) {
                $mapped[$column] = null;
            }
        }

        return $mapped;
    }

    /**
     * @return list<string>
     */
    public function mappedCustomerColumns(): array
    {
        $columns = [];

        foreach (config('hubspot.company_field_map', []) as $definition) {
            $column = $definition['column'] ?? null;

            if (is_string($column) && $column !== '') {
                $columns[] = $column;
            }
        }

        return array_values(array_unique($columns));
    }

    /**
     * @param mixed $value
     */
    protected function castValue(mixed $value, string $type): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        return match ($type) {
            'string' => $this->nullableString($value),
            'int', 'integer' => (int) $value,
            'float', 'decimal' => (float) $value,
            'bool', 'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'datetime' => $this->parseHubSpotDate($value),
            default => $this->nullableString($value),
        };
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

        if (is_numeric($value)) {
            return CarbonImmutable::createFromTimestampMs((int) $value);
        }

        return CarbonImmutable::parse((string) $value);
    }
}

<?php

namespace App\Integrations\HubSpot;

class HubSpotCompanyPropertyList
{
    /**
     * @return list<string>
     */
    public static function syncedProperties(): array
    {
        return array_keys(config('hubspot.company_field_map', []));
    }
}

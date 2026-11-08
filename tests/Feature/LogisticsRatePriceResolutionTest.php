<?php

namespace Tests\Feature;

use App\Models\LogisticsProvider;
use App\Models\LogisticsRate;
use App\Models\LogisticsRatePrice;
use App\Models\LogisticsRateWeightBracket;
use App\Models\LogisticsRateZone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogisticsRatePriceResolutionTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolves_the_redypack_reference_price_for_a_70kg_bracket_in_zone_4(): void
    {
        $provider = LogisticsProvider::create(['name' => 'Redypack', 'type' => 'national_carrier']);

        $rate = LogisticsRate::create([
            'logistics_provider_id' => $provider->id,
            'name' => 'Tarifa nacional Málaga',
            'service_type' => 'other',
            'unit_of_measure' => 'kg',
            'pricing_type' => 'matrix',
            'is_active' => true,
        ]);

        $version = $rate->versions()->create([
            'currency' => 'EUR',
            'vat_included' => false,
            'valid_from' => '2026-01-01',
        ]);

        $zone4 = LogisticsRateZone::create(['logistics_rate_id' => $rate->id, 'code' => '4']);
        $zone10 = LogisticsRateZone::create(['logistics_rate_id' => $rate->id, 'code' => '10']);

        $bracket60 = LogisticsRateWeightBracket::create(['logistics_rate_version_id' => $version->id, 'up_to_kg' => 60]);
        $bracket70 = LogisticsRateWeightBracket::create(['logistics_rate_version_id' => $version->id, 'up_to_kg' => 70]);

        LogisticsRatePrice::create(['logistics_rate_weight_bracket_id' => $bracket60->id, 'logistics_rate_zone_id' => $zone4->id, 'price' => 20.61]);
        LogisticsRatePrice::create(['logistics_rate_weight_bracket_id' => $bracket70->id, 'logistics_rate_zone_id' => $zone4->id, 'price' => 23.06]);
        LogisticsRatePrice::create(['logistics_rate_weight_bracket_id' => $bracket70->id, 'logistics_rate_zone_id' => $zone10->id, 'price' => 70.39]);

        $billableWeight = 65;

        $price = LogisticsRatePrice::query()
            ->join('logistics_rate_weight_brackets', 'logistics_rate_weight_brackets.id', '=', 'logistics_rate_prices.logistics_rate_weight_bracket_id')
            ->where('logistics_rate_weight_brackets.logistics_rate_version_id', $version->id)
            ->where('logistics_rate_weight_brackets.up_to_kg', '>=', $billableWeight)
            ->where('logistics_rate_prices.logistics_rate_zone_id', $zone4->id)
            ->orderBy('logistics_rate_weight_brackets.up_to_kg')
            ->value('logistics_rate_prices.price');

        $this->assertSame('23.06', $price);
    }
}

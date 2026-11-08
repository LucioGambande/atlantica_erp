<?php

namespace Tests\Feature;

use App\Models\LogisticsLocation;
use App\Models\LogisticsProvider;
use App\Models\LogisticsRate;
use App\Models\LogisticsRateZone;
use App\Models\LogisticsRateZoneLocation;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogisticsRateZoneTest extends TestCase
{
    use RefreshDatabase;

    public function test_zone_belongs_to_rate_and_can_have_locations_assigned(): void
    {
        $rate = $this->rate();
        $zone = LogisticsRateZone::create(['logistics_rate_id' => $rate->id, 'code' => '4']);
        $madrid = $this->location('MADRID');

        $zone->locations()->attach($madrid->id, ['logistics_rate_id' => $rate->id]);

        $this->assertSame($rate->id, $zone->rate->id);
        $this->assertSame('MADRID', $zone->locations->sole()->name);
    }

    public function test_a_location_cannot_be_assigned_to_two_zones_of_the_same_rate(): void
    {
        $rate = $this->rate();
        $zoneA = LogisticsRateZone::create(['logistics_rate_id' => $rate->id, 'code' => '1']);
        $zoneB = LogisticsRateZone::create(['logistics_rate_id' => $rate->id, 'code' => '2']);
        $madrid = $this->location('MADRID');

        LogisticsRateZoneLocation::create([
            'logistics_rate_id' => $rate->id,
            'logistics_rate_zone_id' => $zoneA->id,
            'logistics_location_id' => $madrid->id,
        ]);

        $this->expectException(QueryException::class);

        LogisticsRateZoneLocation::create([
            'logistics_rate_id' => $rate->id,
            'logistics_rate_zone_id' => $zoneB->id,
            'logistics_location_id' => $madrid->id,
        ]);
    }

    public function test_the_same_location_can_belong_to_zones_of_different_rates(): void
    {
        $rateA = $this->rate();
        $rateB = $this->rate();
        $madrid = $this->location('MADRID');

        LogisticsRateZoneLocation::create([
            'logistics_rate_id' => $rateA->id,
            'logistics_rate_zone_id' => LogisticsRateZone::create(['logistics_rate_id' => $rateA->id, 'code' => '4'])->id,
            'logistics_location_id' => $madrid->id,
        ]);

        LogisticsRateZoneLocation::create([
            'logistics_rate_id' => $rateB->id,
            'logistics_rate_zone_id' => LogisticsRateZone::create(['logistics_rate_id' => $rateB->id, 'code' => '2'])->id,
            'logistics_location_id' => $madrid->id,
        ]);

        $this->assertSame(2, LogisticsRateZoneLocation::where('logistics_location_id', $madrid->id)->count());
    }

    private function rate(): LogisticsRate
    {
        $provider = LogisticsProvider::create(['name' => 'Redypack', 'type' => 'national_carrier']);

        return LogisticsRate::create([
            'logistics_provider_id' => $provider->id,
            'name' => 'Tarifa nacional',
            'service_type' => 'other',
            'unit_of_measure' => 'kg',
            'pricing_type' => 'matrix',
            'is_active' => true,
        ]);
    }

    private function location(string $name): LogisticsLocation
    {
        return LogisticsLocation::create([
            'name' => $name,
            'country' => 'ES',
            'category' => 'peninsula',
            'volumetric_kg_per_m3' => 270,
        ]);
    }
}

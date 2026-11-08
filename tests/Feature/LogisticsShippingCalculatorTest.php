<?php

namespace Tests\Feature;

use App\Models\LogisticsLocation;
use App\Models\LogisticsProvider;
use App\Models\LogisticsRate;
use App\Models\LogisticsRatePrice;
use App\Models\LogisticsRateVersion;
use App\Models\LogisticsRateWeightBracket;
use App\Models\LogisticsRateZone;
use App\Models\LogisticsRateZoneLocation;
use App\Services\LogisticsShippingCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class LogisticsShippingCalculatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_flat_rate_multiplies_price_by_quantity(): void
    {
        $rate = $this->flatRate();
        $version = $rate->versions()->create([
            'price' => 50,
            'currency' => 'EUR',
            'vat_included' => false,
            'valid_from' => '2026-01-01',
        ]);

        $result = app(LogisticsShippingCalculator::class)->calculate($rate, $version, quantity: 3);

        $this->assertSame(150.0, $result['total']);
    }

    public function test_flat_rate_respects_minimum_cost(): void
    {
        $rate = $this->flatRate();
        $version = $rate->versions()->create([
            'price' => 10,
            'minimum_cost' => 80,
            'currency' => 'EUR',
            'vat_included' => false,
            'valid_from' => '2026-01-01',
        ]);

        $result = app(LogisticsShippingCalculator::class)->calculate($rate, $version, quantity: 2);

        $this->assertSame(80.0, $result['base_price']);
    }

    public function test_matrix_rate_resolves_bracket_and_zone_like_the_redypack_tariff(): void
    {
        [$rate, $version, $madrid] = $this->matrixRateWithZonesAndPrices();

        $result = app(LogisticsShippingCalculator::class)->calculate(
            $rate,
            $version,
            destinationLocationId: $madrid->id,
            weightKg: 65,
        );

        $this->assertSame(23.06, $result['base_price']);
        $this->assertSame('4', $result['zone']);
        $this->assertSame(65.0, $result['billable_weight']);
    }

    public function test_matrix_rate_uses_volumetric_weight_when_it_exceeds_actual_weight(): void
    {
        [$rate, $version, $madrid] = $this->matrixRateWithZonesAndPrices();

        // 0.25 m3 * 270 kg/m3 = 67.5 kg volumétrico > 40 kg reales.
        $result = app(LogisticsShippingCalculator::class)->calculate(
            $rate,
            $version,
            destinationLocationId: $madrid->id,
            weightKg: 40,
            volumeM3: 0.25,
        );

        $this->assertSame(67.5, $result['billable_weight']);
        $this->assertSame(23.06, $result['base_price']);
    }

    public function test_matrix_rate_throws_when_destination_is_not_mapped_to_any_zone(): void
    {
        [$rate, $version] = $this->matrixRateWithZonesAndPrices();
        $sevilla = LogisticsLocation::create(['name' => 'SEVILLA', 'country' => 'ES', 'category' => 'peninsula', 'volumetric_kg_per_m3' => 270]);

        $this->expectException(RuntimeException::class);

        app(LogisticsShippingCalculator::class)->calculate($rate, $version, destinationLocationId: $sevilla->id, weightKg: 10);
    }

    public function test_mandatory_and_optional_surcharges_apply_correctly(): void
    {
        $rate = $this->flatRate();
        $version = $rate->versions()->create([
            'price' => 100,
            'currency' => 'EUR',
            'vat_included' => false,
            'valid_from' => '2026-01-01',
            'surcharges' => [
                ['concept' => 'Seguro', 'type' => 'percentage', 'rate' => 8, 'min' => 0.60],
                ['concept' => 'Reembolso', 'type' => 'percentage', 'rate' => 3, 'min' => 3, 'max' => 60.10, 'optional' => true],
            ],
        ]);

        $withoutOptional = app(LogisticsShippingCalculator::class)->calculate($rate, $version, quantity: 1);
        $this->assertCount(1, $withoutOptional['surcharges']);
        $this->assertSame(8.0, $withoutOptional['surcharges'][0]['amount']);
        $this->assertSame(108.0, $withoutOptional['total']);

        $withOptional = app(LogisticsShippingCalculator::class)->calculate(
            $rate,
            $version,
            quantity: 1,
            includeOptionalSurcharges: ['Reembolso'],
        );
        $this->assertCount(2, $withOptional['surcharges']);
        $this->assertSame(111.0, $withOptional['total']);
    }

    private function provider(): LogisticsProvider
    {
        return LogisticsProvider::create([
            'name' => 'Redypack Málaga',
            'type' => 'national_carrier',
        ]);
    }

    private function flatRate(): LogisticsRate
    {
        return LogisticsRate::create([
            'logistics_provider_id' => $this->provider()->id,
            'name' => 'Pallet nacional',
            'service_type' => 'ftl',
            'unit_of_measure' => 'pallet',
            'pricing_type' => 'flat',
            'is_active' => true,
        ]);
    }

    /**
     * @return array{0: LogisticsRate, 1: LogisticsRateVersion, 2: LogisticsLocation}
     */
    private function matrixRateWithZonesAndPrices(): array
    {
        $rate = LogisticsRate::create([
            'logistics_provider_id' => $this->provider()->id,
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

        $madrid = LogisticsLocation::create(['name' => 'MADRID', 'country' => 'ES', 'category' => 'peninsula', 'volumetric_kg_per_m3' => 270]);

        LogisticsRateZoneLocation::create([
            'logistics_rate_id' => $rate->id,
            'logistics_rate_zone_id' => $zone4->id,
            'logistics_location_id' => $madrid->id,
        ]);

        $bracket60 = LogisticsRateWeightBracket::create(['logistics_rate_version_id' => $version->id, 'up_to_kg' => 60]);
        $bracket70 = LogisticsRateWeightBracket::create(['logistics_rate_version_id' => $version->id, 'up_to_kg' => 70]);

        LogisticsRatePrice::create(['logistics_rate_weight_bracket_id' => $bracket60->id, 'logistics_rate_zone_id' => $zone4->id, 'price' => 20.61]);
        LogisticsRatePrice::create(['logistics_rate_weight_bracket_id' => $bracket70->id, 'logistics_rate_zone_id' => $zone4->id, 'price' => 23.06]);

        return [$rate, $version, $madrid];
    }
}

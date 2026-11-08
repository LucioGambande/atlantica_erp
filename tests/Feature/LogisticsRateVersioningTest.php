<?php

namespace Tests\Feature;

use App\Models\LogisticsLocation;
use App\Models\LogisticsProvider;
use App\Models\LogisticsRate;
use App\Services\LogisticsRateVersionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class LogisticsRateVersioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_rate_belongs_to_provider_and_scope_active_filters_correctly(): void
    {
        $provider = LogisticsProvider::create([
            'name' => 'Transportes del Sur',
            'type' => 'national_carrier',
        ]);

        $activeRate = $this->rate($provider, ['is_active' => true]);
        $this->rate($provider, ['is_active' => false]);

        $this->assertSame($provider->id, $activeRate->provider->id);
        $this->assertSame(1, LogisticsRate::active()->count());
    }

    public function test_current_version_returns_null_when_no_versions_exist(): void
    {
        $rate = $this->rate($this->provider());

        $this->assertNull($rate->currentVersion());
    }

    public function test_first_version_of_a_rate_does_not_need_to_close_anything(): void
    {
        $rate = $this->rate($this->provider());

        $version = app(LogisticsRateVersionService::class)->createNewVersion($rate, [
            'price' => 100,
            'currency' => 'EUR',
            'vat_included' => false,
            'valid_from' => '2026-01-01',
        ]);

        $this->assertNull($version->valid_until);
        $this->assertSame($version->id, $rate->currentVersion()->id);
    }

    public function test_creating_a_new_version_closes_the_previous_one_without_deleting_it(): void
    {
        $rate = $this->rate($this->provider());
        $service = app(LogisticsRateVersionService::class);

        $versionA = $service->createNewVersion($rate, [
            'price' => 100,
            'currency' => 'EUR',
            'vat_included' => false,
            'valid_from' => '2026-01-01',
        ]);

        $versionB = $service->createNewVersion($rate, [
            'price' => 120,
            'currency' => 'EUR',
            'vat_included' => false,
            'valid_from' => '2026-06-01',
        ]);

        $this->assertDatabaseHas('logistics_rate_versions', ['id' => $versionA->id]);
        $this->assertSame('2026-05-31', $versionA->fresh()->valid_until->toDateString());
        $this->assertNull($versionB->fresh()->valid_until);
        $this->assertSame($versionB->id, $rate->currentVersion()->id);
    }

    public function test_cannot_create_a_new_version_with_valid_from_before_the_current_versions_valid_from(): void
    {
        $rate = $this->rate($this->provider());
        $service = app(LogisticsRateVersionService::class);

        $service->createNewVersion($rate, [
            'price' => 100,
            'currency' => 'EUR',
            'vat_included' => false,
            'valid_from' => '2026-06-01',
        ]);

        $this->expectException(RuntimeException::class);

        $service->createNewVersion($rate, [
            'price' => 90,
            'currency' => 'EUR',
            'vat_included' => false,
            'valid_from' => '2026-01-01',
        ]);
    }

    public function test_matrix_pricing_type_allows_a_version_without_a_flat_price(): void
    {
        $rate = $this->rate($this->provider(), ['pricing_type' => 'matrix']);

        $version = app(LogisticsRateVersionService::class)->createNewVersion($rate, [
            'currency' => 'EUR',
            'vat_included' => false,
            'valid_from' => '2026-01-01',
        ]);

        $this->assertNull($version->price);
        $this->assertSame('matrix', $rate->pricing_type);
    }

    public function test_rate_resolves_origin_and_destination_locations(): void
    {
        $madrid = LogisticsLocation::create(['name' => 'MADRID', 'country' => 'ES', 'category' => 'peninsula', 'volumetric_kg_per_m3' => 270]);
        $lisboa = LogisticsLocation::create(['name' => 'LISBOA', 'country' => 'PT', 'category' => 'portugal', 'volumetric_kg_per_m3' => 333]);

        $rate = $this->rate($this->provider(), [
            'origin_location_id' => $madrid->id,
            'destination_location_id' => $lisboa->id,
        ]);

        $this->assertSame('MADRID', $rate->originLocation->name);
        $this->assertSame('LISBOA', $rate->destinationLocation->name);
    }

    private function provider(): LogisticsProvider
    {
        return LogisticsProvider::create([
            'name' => 'Transportes del Sur',
            'type' => 'national_carrier',
        ]);
    }

    private function rate(LogisticsProvider $provider, array $overrides = []): LogisticsRate
    {
        return LogisticsRate::create([
            'logistics_provider_id' => $provider->id,
            'name' => 'Madrid - Lisboa',
            'service_type' => 'ftl',
            'unit_of_measure' => 'pallet',
            'is_active' => true,
            ...$overrides,
        ]);
    }
}

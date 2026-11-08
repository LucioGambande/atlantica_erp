<?php

namespace Tests\Feature;

use App\Models\LogisticsLocation;
use Database\Seeders\LogisticsLocationsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogisticsLocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_scope_of_category_filters_correctly(): void
    {
        LogisticsLocation::create(['name' => 'MADRID', 'country' => 'ES', 'category' => 'peninsula', 'volumetric_kg_per_m3' => 270]);
        LogisticsLocation::create(['name' => 'PALMA', 'country' => 'ES', 'category' => 'baleares', 'volumetric_kg_per_m3' => 333]);

        $this->assertSame(1, LogisticsLocation::ofCategory('baleares')->count());
    }

    public function test_seeder_is_idempotent_and_loads_key_locations(): void
    {
        $this->seed(LogisticsLocationsSeeder::class);
        $firstCount = LogisticsLocation::count();

        $this->seed(LogisticsLocationsSeeder::class);

        $this->assertSame($firstCount, LogisticsLocation::count());
        $this->assertTrue(LogisticsLocation::where('name', 'MADRID')->exists());
        $this->assertTrue(LogisticsLocation::where('name', 'MALAGA')->exists());
        $this->assertTrue(LogisticsLocation::where('name', 'LISBOA')->where('country', 'PT')->exists());

        $palma = LogisticsLocation::where('name', 'PALMA')->sole();
        $this->assertSame('333.00', $palma->volumetric_kg_per_m3);

        $madrid = LogisticsLocation::where('name', 'MADRID')->sole();
        $this->assertSame('270.00', $madrid->volumetric_kg_per_m3);
    }
}

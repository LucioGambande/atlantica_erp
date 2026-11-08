<?php

namespace Database\Seeders;

use App\Models\LogisticsLocation;
use Illuminate\Database\Seeder;

class LogisticsLocationsSeeder extends Seeder
{
    /**
     * Catálogo base de ubicaciones para tarifas logísticas nacionales,
     * tomado de una tarifa real de referencia (provincias españolas +
     * Portugal + Baleares/Ceuta-Melilla). Reutilizable entre tarifas: los
     * baremos/zonas de cada transportista se asignan después, por tarifa,
     * en `logistics_rate_zones`/`logistics_rate_zone_locations`.
     */
    private const PENINSULA = [
        'ALBACETE', 'ALGECIRAS', 'ALICANTE', 'ALMERIA', 'ASTURIAS', 'AVILA',
        'BARCELONA', 'BILBAO', 'BLANES', 'BURGOS', 'CACERES', 'CADIZ',
        'CASTELLON', 'CERDANYA', 'CIUDAD REAL', 'CORDOBA', 'CORUÑA', 'CUENCA',
        'GIRONA', 'GRANADA', 'GUADALAJARA', 'HUELVA', 'HUESCA', 'JAEN',
        'LEON', 'LLEIDA', 'LOGROÑO', 'LUGO', 'MADRID', 'MALAGA', 'MANRESA',
        'MERIDA', 'MURCIA', 'ORENSE', 'PALENCIA', 'PAMPLONA', 'RIPOLL',
        'SALAMANCA', 'SAN SEBASTIAN', 'SANTANDER', 'SANTIAGO', 'SEGOVIA',
        'SEVILLA', 'SORIA', 'TARRAGONA', 'TERUEL', 'TOLEDO', 'VALENCIA',
        "VALL D'ARAN", 'VALLADOLID', 'VIGO', 'VITORIA', 'ZAMORA', 'ZARAGOZA',
    ];

    private const BALEARES = ['IBIZA', 'MENORCA', 'PALMA'];

    private const CEUTA_MELILLA = ['MELILLA'];

    private const PORTUGAL = ['LISBOA', 'PORTO'];

    public function run(): void
    {
        foreach (self::PENINSULA as $name) {
            $this->upsert($name, 'ES', 'peninsula', 270);
        }

        foreach (self::BALEARES as $name) {
            $this->upsert($name, 'ES', 'baleares', 333);
        }

        foreach (self::CEUTA_MELILLA as $name) {
            $this->upsert($name, 'ES', 'ceuta_melilla', 333);
        }

        foreach (self::PORTUGAL as $name) {
            $this->upsert($name, 'PT', 'portugal', 333);
        }
    }

    private function upsert(string $name, string $country, string $category, float $volumetricKgPerM3): void
    {
        LogisticsLocation::query()->updateOrCreate(
            ['name' => $name, 'country' => $country],
            ['category' => $category, 'volumetric_kg_per_m3' => $volumetricKgPerM3],
        );
    }
}

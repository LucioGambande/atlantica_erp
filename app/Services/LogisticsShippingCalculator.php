<?php

namespace App\Services;

use App\Models\LogisticsRate;
use App\Models\LogisticsRatePrice;
use App\Models\LogisticsRateVersion;
use App\Models\LogisticsRateZoneLocation;
use RuntimeException;

class LogisticsShippingCalculator
{
    /**
     * Calcula el precio estimado de un envío contra una tarifa vigente.
     *
     * Para tarifas "flat" (precio fijo por unidad): se usa $quantity
     * (cantidad de pallets/cajas/km/etc, según unit_of_measure de la tarifa).
     *
     * Para tarifas "matrix" (peso/volumen x zona, ej. tarifas de
     * transportistas nacionales): se usa $destinationLocationId + $weightKg
     * (+ $volumeM3 opcional) para resolver la zona (vía
     * logistics_rate_zone_locations) y el tramo de peso/precio (vía
     * logistics_rate_weight_brackets/logistics_rate_prices) de la versión.
     *
     * @return array{base_price: float, billable_weight: ?float, zone: ?string, surcharges: array<int, array{concept: string, amount: float}>, total: float, currency: string, vat_included: bool}
     */
    public function calculate(
        LogisticsRate $rate,
        LogisticsRateVersion $version,
        ?int $destinationLocationId = null,
        ?float $weightKg = null,
        ?float $volumeM3 = null,
        float $quantity = 1,
        array $includeOptionalSurcharges = [],
    ): array {
        $billableWeight = null;
        $zoneCode = null;

        if ($rate->pricing_type === 'matrix') {
            [$basePrice, $billableWeight, $zoneCode] = $this->resolveMatrixPrice($rate, $version, $destinationLocationId, $weightKg, $volumeM3);
        } else {
            if ($version->price === null) {
                throw new RuntimeException('Esta versión de tarifa no tiene un precio fijo cargado.');
            }

            $basePrice = (float) $version->price * $quantity;
        }

        if ($version->minimum_cost !== null && $basePrice < (float) $version->minimum_cost) {
            $basePrice = (float) $version->minimum_cost;
        }

        $surcharges = $this->applySurcharges($version->surcharges ?? [], $basePrice, $includeOptionalSurcharges);

        $total = $basePrice + array_sum(array_column($surcharges, 'amount'));

        return [
            'base_price' => round($basePrice, 2),
            'billable_weight' => $billableWeight,
            'zone' => $zoneCode,
            'surcharges' => $surcharges,
            'total' => round($total, 2),
            'currency' => $version->currency,
            'vat_included' => $version->vat_included,
        ];
    }

    /**
     * @return array{0: float, 1: float, 2: string}
     */
    private function resolveMatrixPrice(LogisticsRate $rate, LogisticsRateVersion $version, ?int $destinationLocationId, ?float $weightKg, ?float $volumeM3): array
    {
        if ($destinationLocationId === null) {
            throw new RuntimeException('Indicá un destino para calcular una tarifa de tipo matriz.');
        }

        if ($weightKg === null) {
            throw new RuntimeException('Indicá el peso del pedido para calcular una tarifa de tipo matriz.');
        }

        $zoneLocation = LogisticsRateZoneLocation::query()
            ->where('logistics_rate_id', $rate->id)
            ->where('logistics_location_id', $destinationLocationId)
            ->with(['zone', 'location'])
            ->first();

        if ($zoneLocation === null) {
            throw new RuntimeException('El destino elegido no está mapeado a ninguna zona de esta tarifa.');
        }

        $billableWeight = $weightKg;

        if ($volumeM3 !== null) {
            $volumetricFactor = (float) $zoneLocation->location->volumetric_kg_per_m3;
            $billableWeight = max($weightKg, $volumeM3 * $volumetricFactor);
        }

        $price = LogisticsRatePrice::query()
            ->join('logistics_rate_weight_brackets', 'logistics_rate_weight_brackets.id', '=', 'logistics_rate_prices.logistics_rate_weight_bracket_id')
            ->where('logistics_rate_weight_brackets.logistics_rate_version_id', $version->id)
            ->where('logistics_rate_weight_brackets.up_to_kg', '>=', $billableWeight)
            ->where('logistics_rate_prices.logistics_rate_zone_id', $zoneLocation->logistics_rate_zone_id)
            ->orderBy('logistics_rate_weight_brackets.up_to_kg')
            ->value('logistics_rate_prices.price');

        if ($price === null) {
            throw new RuntimeException('No hay precio cargado para esa combinación de peso y zona en esta tarifa.');
        }

        return [(float) $price, $billableWeight, $zoneLocation->zone->code];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rules
     * @return array<int, array{concept: string, amount: float}>
     */
    private function applySurcharges(array $rules, float $basePrice, array $includeOptionalSurcharges): array
    {
        $applied = [];

        foreach ($rules as $rule) {
            $concept = $rule['concept'] ?? 'Recargo';

            if (($rule['optional'] ?? false) && ! in_array($concept, $includeOptionalSurcharges, true)) {
                continue;
            }

            $amount = match ($rule['type'] ?? 'fixed') {
                'percentage' => $basePrice * ((float) ($rule['rate'] ?? 0) / 100),
                default => (float) ($rule['amount'] ?? 0),
            };

            if (isset($rule['min'])) {
                $amount = max($amount, (float) $rule['min']);
            }

            if (isset($rule['max'])) {
                $amount = min($amount, (float) $rule['max']);
            }

            $applied[] = [
                'concept' => $concept,
                'amount' => round($amount, 2),
            ];
        }

        return $applied;
    }
}

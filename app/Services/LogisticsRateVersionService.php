<?php

namespace App\Services;

use App\Models\LogisticsRate;
use App\Models\LogisticsRateVersion;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class LogisticsRateVersionService
{
    /**
     * Crea una nueva versión de precio para la tarifa. Si había una versión
     * vigente, la cierra (valid_until = día anterior) en vez de pisarla:
     * el historial de precios nunca se edita ni se borra.
     */
    public function createNewVersion(LogisticsRate $rate, array $data): LogisticsRateVersion
    {
        return DB::transaction(function () use ($rate, $data): LogisticsRateVersion {
            $validFrom = Carbon::parse($data['valid_from'] ?? now());

            $current = $rate->currentVersion();

            if ($current !== null) {
                if ($validFrom->lt($current->valid_from)) {
                    throw new RuntimeException(
                        'La nueva versión no puede tener una fecha de vigencia anterior a la versión vigente actual.'
                    );
                }

                $current->update([
                    'valid_until' => $validFrom->copy()->subDay(),
                ]);
            }

            return $rate->versions()->create([
                ...$data,
                'valid_from' => $validFrom,
                'valid_until' => null,
            ]);
        });
    }
}

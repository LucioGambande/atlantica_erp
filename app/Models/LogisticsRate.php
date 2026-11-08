<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LogisticsRate extends Model
{
    use HasFactory, SoftDeletes;

    public const SERVICE_TYPES = [
        'ftl' => 'Camión completo (FTL)',
        'ltl' => 'Carga parcial (LTL)',
        'air' => 'Aéreo',
        'sea' => 'Marítimo',
        'storage' => 'Almacenaje',
        'handling' => 'Manipulación',
        'other' => 'Otro',
    ];

    public const UNITS = [
        'pallet' => 'Pallet',
        'box' => 'Caja',
        'kg' => 'Kilogramo',
        'shipment' => 'Expedición',
        'trip' => 'Viaje',
        'day' => 'Día',
        'month' => 'Mes',
        'km' => 'Kilómetro',
        'operation' => 'Operación',
    ];

    public const PRICING_TYPES = [
        'flat' => 'Precio fijo por unidad',
        'matrix' => 'Matriz por peso/volumen y zona de destino',
    ];

    protected $fillable = [
        'logistics_provider_id',
        'name',
        'service_type',
        'origin_location_id',
        'destination_location_id',
        'unit_of_measure',
        'pricing_type',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(LogisticsProvider::class, 'logistics_provider_id');
    }

    public function originLocation(): BelongsTo
    {
        return $this->belongsTo(LogisticsLocation::class, 'origin_location_id');
    }

    public function destinationLocation(): BelongsTo
    {
        return $this->belongsTo(LogisticsLocation::class, 'destination_location_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(LogisticsRateVersion::class);
    }

    public function zones(): HasMany
    {
        return $this->hasMany(LogisticsRateZone::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Versión con precio vigente hoy, o null si no hay ninguna cargada.
     * Si la relación `versions` ya viene cargada (eager load filtrado por
     * vigencia desde el Resource), se usa esa colección en memoria en vez
     * de disparar una query nueva por fila.
     */
    public function currentVersion(): ?LogisticsRateVersion
    {
        if ($this->relationLoaded('versions')) {
            return $this->versions->first();
        }

        return $this->versions()->current()->orderByDesc('valid_from')->first();
    }
}

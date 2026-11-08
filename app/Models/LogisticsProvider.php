<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LogisticsProvider extends Model
{
    use HasFactory, SoftDeletes;

    public const TYPES = [
        'national_carrier' => 'Transportista nacional',
        'international_carrier' => 'Transportista internacional',
        'warehouse' => 'Almacén',
        'fiscal_deposit' => 'Depósito fiscal',
        'freight_forwarder' => 'Transitario / Forwarder',
        'other' => 'Otro',
    ];

    protected $fillable = [
        'name',
        'tax_id',
        'email',
        'phone',
        'address',
        'type',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function rates(): HasMany
    {
        return $this->hasMany(LogisticsRate::class);
    }

    public function warehouses(): HasMany
    {
        return $this->hasMany(Warehouse::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}

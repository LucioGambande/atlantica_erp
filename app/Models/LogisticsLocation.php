<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LogisticsLocation extends Model
{
    use HasFactory;

    public const CATEGORIES = [
        'peninsula' => 'Península',
        'baleares' => 'Baleares',
        'canarias' => 'Canarias',
        'ceuta_melilla' => 'Ceuta y Melilla',
        'portugal' => 'Portugal',
    ];

    protected $fillable = [
        'name',
        'country',
        'category',
        'volumetric_kg_per_m3',
    ];

    protected function casts(): array
    {
        return [
            'volumetric_kg_per_m3' => 'decimal:2',
        ];
    }

    public function scopeOfCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    public function rateZoneLocations(): HasMany
    {
        return $this->hasMany(LogisticsRateZoneLocation::class);
    }
}

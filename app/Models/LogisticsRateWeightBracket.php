<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LogisticsRateWeightBracket extends Model
{
    use HasFactory;

    protected $fillable = [
        'logistics_rate_version_id',
        'up_to_kg',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'up_to_kg' => 'decimal:2',
        ];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(LogisticsRateVersion::class, 'logistics_rate_version_id');
    }

    public function prices(): HasMany
    {
        return $this->hasMany(LogisticsRatePrice::class);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('up_to_kg');
    }
}

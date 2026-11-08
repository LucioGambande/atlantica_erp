<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LogisticsRateZone extends Model
{
    use HasFactory;

    protected $fillable = [
        'logistics_rate_id',
        'code',
        'name',
    ];

    public function rate(): BelongsTo
    {
        return $this->belongsTo(LogisticsRate::class, 'logistics_rate_id');
    }

    public function prices(): HasMany
    {
        return $this->hasMany(LogisticsRatePrice::class);
    }

    public function locations(): BelongsToMany
    {
        return $this->belongsToMany(LogisticsLocation::class, 'logistics_rate_zone_locations')
            ->withTimestamps();
    }
}

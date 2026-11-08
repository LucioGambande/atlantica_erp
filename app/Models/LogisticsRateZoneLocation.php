<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LogisticsRateZoneLocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'logistics_rate_id',
        'logistics_rate_zone_id',
        'logistics_location_id',
    ];

    public function rate(): BelongsTo
    {
        return $this->belongsTo(LogisticsRate::class, 'logistics_rate_id');
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(LogisticsRateZone::class, 'logistics_rate_zone_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(LogisticsLocation::class, 'logistics_location_id');
    }
}

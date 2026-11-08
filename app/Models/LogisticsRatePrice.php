<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LogisticsRatePrice extends Model
{
    use HasFactory;

    protected $fillable = [
        'logistics_rate_weight_bracket_id',
        'logistics_rate_zone_id',
        'price',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
        ];
    }

    public function weightBracket(): BelongsTo
    {
        return $this->belongsTo(LogisticsRateWeightBracket::class, 'logistics_rate_weight_bracket_id');
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(LogisticsRateZone::class, 'logistics_rate_zone_id');
    }
}

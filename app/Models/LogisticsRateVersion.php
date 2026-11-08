<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LogisticsRateVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'logistics_rate_id',
        'price',
        'currency',
        'vat_included',
        'minimum_cost',
        'surcharges',
        'conditions',
        'source_document_path',
        'valid_from',
        'valid_until',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'minimum_cost' => 'decimal:2',
            'vat_included' => 'boolean',
            'surcharges' => 'array',
            'valid_from' => 'date',
            'valid_until' => 'date',
        ];
    }

    public function rate(): BelongsTo
    {
        return $this->belongsTo(LogisticsRate::class, 'logistics_rate_id');
    }

    public function weightBrackets(): HasMany
    {
        return $this->hasMany(LogisticsRateWeightBracket::class);
    }

    /**
     * Versiones vigentes a la fecha dada (hoy por defecto).
     */
    public function scopeCurrent(Builder $query, ?string $asOf = null): Builder
    {
        $asOf ??= now()->toDateString();

        return $query
            ->where('valid_from', '<=', $asOf)
            ->where(function (Builder $query) use ($asOf): void {
                $query->whereNull('valid_until')->orWhere('valid_until', '>=', $asOf);
            });
    }

    public function isCurrent(): bool
    {
        $today = now()->toDateString();

        return $this->valid_from->toDateString() <= $today
            && ($this->valid_until === null || $this->valid_until->toDateString() >= $today);
    }
}

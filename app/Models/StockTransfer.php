<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockTransfer extends Model
{
    use HasFactory;

    public const STATUSES = [
        'draft' => 'Borrador',
        'completed' => 'Completado',
        'cancelled' => 'Cancelado',
    ];

    protected $fillable = [
        'origin_warehouse_id',
        'destination_warehouse_id',
        'status',
        'stock_movements_recorded',
        'transferred_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'stock_movements_recorded' => 'boolean',
            'transferred_at' => 'datetime',
        ];
    }

    public function originWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'origin_warehouse_id');
    }

    public function destinationWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'destination_warehouse_id');
    }

    public function stockTransferItems(): HasMany
    {
        return $this->hasMany(StockTransferItem::class);
    }

    public function hasBeenCompleted(): bool
    {
        return $this->status === 'completed';
    }
}

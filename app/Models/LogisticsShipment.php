<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use RuntimeException;

class LogisticsShipment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'logistics_provider_id',
        'logistics_rate_id',
        'logistics_rate_version_id',
        'invoice_id',
        'order_id',
        'purchase_invoice_id',
        'origin_location_id',
        'destination_location_id',
        'weight_kg',
        'volume_m3',
        'calculated_cost',
        'actual_cost',
        'currency',
        'shipped_at',
        'notes',
        'source_document_path',
    ];

    protected function casts(): array
    {
        return [
            'weight_kg' => 'decimal:2',
            'volume_m3' => 'decimal:3',
            'calculated_cost' => 'decimal:2',
            'actual_cost' => 'decimal:2',
            'shipped_at' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (LogisticsShipment $shipment): void {
            if ($shipment->invoice_id === null && $shipment->order_id === null && $shipment->purchase_invoice_id === null) {
                throw new RuntimeException('Un envío debe estar vinculado a una factura, un pedido o una compra.');
            }
        });
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(LogisticsProvider::class, 'logistics_provider_id');
    }

    public function rate(): BelongsTo
    {
        return $this->belongsTo(LogisticsRate::class, 'logistics_rate_id');
    }

    public function rateVersion(): BelongsTo
    {
        return $this->belongsTo(LogisticsRateVersion::class, 'logistics_rate_version_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function purchaseInvoice(): BelongsTo
    {
        return $this->belongsTo(PurchaseInvoice::class);
    }

    public function originLocation(): BelongsTo
    {
        return $this->belongsTo(LogisticsLocation::class, 'origin_location_id');
    }

    public function destinationLocation(): BelongsTo
    {
        return $this->belongsTo(LogisticsLocation::class, 'destination_location_id');
    }
}

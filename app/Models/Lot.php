<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Lote de un producto. El código es único por producto: dos productos
 * distintos pueden compartir el mismo código de lote.
 */
class Lot extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'code',
        'manufactured_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'manufactured_at' => 'date',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function invoiceItems(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function purchaseInvoiceItems(): HasMany
    {
        return $this->hasMany(PurchaseInvoiceItem::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * Unidades de este lote que quedan en depósito, derivadas del log de
     * movimientos (mismo criterio que StockService para products.stock).
     */
    public function stockBalance(): int
    {
        return (int) $this->stockMovements()
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'in' THEN quantity ELSE -quantity END), 0) as balance")
            ->value('balance');
    }
}

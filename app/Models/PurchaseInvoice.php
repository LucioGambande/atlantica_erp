<?php

namespace App\Models;

use App\Support\VatTotals;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseInvoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'supplier_id',
        'document_number',
        'status',
        'generates_stock_movement',
        'stock_movements_recorded',
        'total_amount',
        'received_at',
    ];

    protected function casts(): array
    {
        return [
            'generates_stock_movement' => 'boolean',
            'stock_movements_recorded' => 'boolean',
            'total_amount' => 'decimal:2',
            'received_at' => 'datetime',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchaseInvoiceItems(): HasMany
    {
        return $this->hasMany(PurchaseInvoiceItem::class);
    }

    /**
     * La mercadería entró al depósito: el stock de una compra se suma cuando
     * la factura pasa a "recibida" (o directamente a "pagada"), no al
     * cargarla como borrador.
     */
    public function hasEnteredStock(): bool
    {
        return in_array($this->status, ['received', 'paid'], true);
    }

    public function vatRate(): float
    {
        return VatTotals::rate();
    }

    public function netAmount(): float
    {
        $this->loadMissing('purchaseInvoiceItems');

        $fromItems = round((float) $this->purchaseInvoiceItems->sum('total_price'), 2);

        if ($fromItems > 0) {
            return $fromItems;
        }

        return VatTotals::netFromGross((float) $this->total_amount);
    }

    public function grossAmount(): float
    {
        $this->loadMissing('purchaseInvoiceItems');

        $fromItems = round((float) $this->purchaseInvoiceItems->sum('total_price'), 2);

        if ($fromItems > 0) {
            return VatTotals::grossFromNet($fromItems);
        }

        return round((float) $this->total_amount, 2);
    }
}

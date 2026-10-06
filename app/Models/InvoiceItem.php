<?php

namespace App\Models;

use App\Support\LineItemTotals;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'credited_invoice_item_id',
        'legacy_line_id',
        'product_id',
        'description',
        'quantity',
        'unit_price',
        'discount_percent',
        'total_price',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'discount_percent' => 'decimal:2',
            'total_price' => 'decimal:2',
        ];
    }

    protected function discountedTotal(): Attribute
    {
        return Attribute::get(fn (): float => LineItemTotals::discountedLineTotal(
            (float) $this->unit_price,
            (int) $this->quantity,
            (float) $this->discount_percent,
        ));
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Línea original de la factura que esta línea (de una nota de
     * crédito) devuelve, si corresponde.
     */
    public function creditedItem(): BelongsTo
    {
        return $this->belongsTo(self::class, 'credited_invoice_item_id');
    }

    /**
     * Líneas de notas de crédito que devolvieron (total o parcialmente)
     * esta línea de factura.
     */
    public function creditNoteItems(): HasMany
    {
        return $this->hasMany(self::class, 'credited_invoice_item_id');
    }

    public function returnedQuantity(): int
    {
        return (int) $this->creditNoteItems->sum('quantity');
    }

    public function remainingReturnableQuantity(): int
    {
        return max(0, (int) $this->quantity - $this->returnedQuantity());
    }
}

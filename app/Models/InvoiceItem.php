<?php

namespace App\Models;

use App\Support\LineItemTotals;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
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
}

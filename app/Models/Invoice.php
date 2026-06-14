<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'order_id',
        'invoice_number',
        'status',
        'total_amount',
        'issued_at',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'issued_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function invoiceItems(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function recalculateTotalFromItems(): void
    {
        $this->loadMissing('invoiceItems');

        $total = $this->invoiceItems->sum(
            fn (InvoiceItem $item): float => $item->discounted_total,
        );

        $this->update([
            'total_amount' => round($total, 2),
        ]);
    }
}

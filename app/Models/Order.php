<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'status',
        'total_amount',
        'ordered_at',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'ordered_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class)
            ->where('document_type', 'invoice')
            ->whereNull('cancelled_at');
    }

    public function canBeInvoiced(): bool
    {
        return ! $this->invoice()->exists();
    }

    public function recalculateTotalFromItems(): void
    {
        $this->loadMissing('orderItems');

        $total = $this->orderItems->sum(
            fn (OrderItem $item): float => $item->discounted_total,
        );

        $this->update([
            'total_amount' => round($total, 2),
        ]);
    }
}

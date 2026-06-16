<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceListItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'price_list_id',
        'product_id',
        'price',
        'discount_percent',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'discount_percent' => 'decimal:2',
        ];
    }

    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    protected function finalPrice(): Attribute
    {
        return Attribute::get(function (): float {
            $price = (float) $this->price;
            $discount = (float) $this->discount_percent;

            return round($price * (1 - $discount / 100), 2);
        });
    }
}

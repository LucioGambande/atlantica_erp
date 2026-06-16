<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PriceList extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'currency',
        'discount_percent',
        'is_default',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'discount_percent' => 'decimal:2',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (PriceList $priceList): void {
            if (! $priceList->is_default) {
                return;
            }

            static::query()
                ->where('is_default', true)
                ->when($priceList->exists, fn (Builder $query) => $query->whereKeyNot($priceList->id))
                ->update(['is_default' => false]);
        });
    }

    public function items(): HasMany
    {
        return $this->hasMany(PriceListItem::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function getPriceForProduct(Product $product): ?float
    {
        $item = $this->items()->where('product_id', $product->id)->first();

        if ($item === null) {
            return null;
        }

        return (float) $item->final_price;
    }
}

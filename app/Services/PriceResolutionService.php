<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Product;

class PriceResolutionService
{
    public function resolvePrice(Product $product, ?Customer $customer = null): float
    {
        if ($customer === null) {
            return (float) $product->sale_price;
        }

        $priceList = $customer->getEffectivePriceList();

        if ($priceList === null) {
            return (float) $product->sale_price;
        }

        $item = $priceList->items()->where('product_id', $product->id)->first();

        if ($item !== null) {
            return (float) $item->final_price;
        }

        if ((float) $priceList->discount_percent > 0) {
            return round(
                (float) $product->sale_price * (1 - (float) $priceList->discount_percent / 100),
                2,
            );
        }

        return (float) $product->sale_price;
    }

    public function resolvePriceForCustomerId(Product $product, ?int $customerId): float
    {
        if ($customerId === null) {
            return (float) $product->sale_price;
        }

        $customer = Customer::query()->find($customerId);

        return $this->resolvePrice($product, $customer);
    }
}

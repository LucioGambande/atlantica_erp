<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductsSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $products = [
            [
                'name' => 'Catalpa Malbec',
                'sku' => 'WINE-CAT-MAL-750',
                'purchase_price' => 10000.00,
                'stock' => 50,
            ],
            [
                'name' => 'Catalpa Chardonnay',
                'sku' => 'WINE-CAT-CHA-750',
                'purchase_price' => 9800.00,
                'stock' => 50,
            ],
            [
                'name' => 'Atamisque Malbec',
                'sku' => 'WINE-ATA-MAL-750',
                'purchase_price' => 11200.00,
                'stock' => 50,
            ],
            [
                'name' => 'Atamisque Assemblage',
                'sku' => 'WINE-ATA-ASS-750',
                'purchase_price' => 12500.00,
                'stock' => 50,
            ],
        ];

        foreach ($products as $product) {
            $product['sale_price'] = round($product['purchase_price'] * 1.30, 2);

            Product::updateOrCreate(
                ['sku' => $product['sku']],
                $product,
            );
        }
    }
}

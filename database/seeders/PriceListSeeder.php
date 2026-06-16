<?php

namespace Database\Seeders;

use App\Models\PriceList;
use App\Models\Product;
use Illuminate\Database\Seeder;

class PriceListSeeder extends Seeder
{
    public function run(): void
    {
        $general = PriceList::updateOrCreate(
            ['slug' => 'general'],
            [
                'name' => 'General',
                'description' => 'Lista por defecto. Usa el PVP base de cada producto.',
                'currency' => 'EUR',
                'discount_percent' => 0,
                'is_default' => true,
                'is_active' => true,
            ],
        );

        $horeca = PriceList::updateOrCreate(
            ['slug' => 'horeca'],
            [
                'name' => 'HORECA',
                'description' => 'Precios para hostelería. Descuento global del 5% o precios específicos.',
                'currency' => 'EUR',
                'discount_percent' => 5,
                'is_default' => false,
                'is_active' => true,
            ],
        );

        $distribuidores = PriceList::updateOrCreate(
            ['slug' => 'distribuidores'],
            [
                'name' => 'Distribuidores',
                'description' => 'Descuento global del 10% sobre PVP base.',
                'currency' => 'EUR',
                'discount_percent' => 10,
                'is_default' => false,
                'is_active' => true,
            ],
        );

        $horecaSpecificPrices = [
            [
                'skus' => ['CAT-MAL-750', 'WINE-CAT-MAL-750'],
                'price' => 8.79,
                'discount_percent' => 0,
            ],
            [
                'skus' => ['CAT-CHA-750', 'WINE-CAT-CHA-750'],
                'price' => 8.79,
                'discount_percent' => 0,
            ],
            [
                'skus' => ['ATA-MAL-750', 'WINE-ATA-MAL-750'],
                'price' => 12.02,
                'discount_percent' => 0,
            ],
            [
                'skus' => ['ATA-ASS-750', 'WINE-ATA-ASS-750'],
                'price' => 16.81,
                'discount_percent' => 0,
            ],
            [
                'skus' => ['SER-MAL-750'],
                'price' => 5.97,
                'discount_percent' => 0,
            ],
        ];

        foreach ($horecaSpecificPrices as $pricing) {
            $product = Product::query()->whereIn('sku', $pricing['skus'])->first();

            if ($product === null) {
                $this->command->warn('⚠️  Ningún producto encontrado para '.implode(' / ', $pricing['skus']).' — omitido en lista HORECA');

                continue;
            }

            $horeca->items()->updateOrCreate(
                ['product_id' => $product->id],
                [
                    'price' => $pricing['price'],
                    'discount_percent' => $pricing['discount_percent'],
                ],
            );
        }

        $this->command->info("✅ Lista General (ID {$general->id}) — default");
        $this->command->info("✅ Lista HORECA (ID {$horeca->id}) — 5% global + precios específicos");
        $this->command->info("✅ Lista Distribuidores (ID {$distribuidores->id}) — 10% global");
    }
}

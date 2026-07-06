<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Database\Seeder;

class ProductosAtamisqueSeeder extends Seeder
{
    public function run(): void
    {
        $supplier = Supplier::query()->firstOrCreate(
            ['name' => 'Bodega Atamisque'],
        );

        // Fuente: Productos.csv (Atlántica ERP Base)
        $productos = [
            ['sku' => 'SER-MAL-750', 'name' => 'Serbal Malbec', 'sale_price' => 6.28, 'purchase_price' => 0, 'stock' => 0],
            ['sku' => 'SER-CAB-750', 'name' => 'Serbal Cabernet Franc', 'sale_price' => 6.28, 'purchase_price' => 0, 'stock' => 0],
            ['sku' => 'SER-PIN-750', 'name' => 'Serbal Pinot Noir', 'sale_price' => 6.28, 'purchase_price' => 0, 'stock' => 0],
            ['sku' => 'SER-CHA-750', 'name' => 'Serbal Chardonnay', 'sale_price' => 6.28, 'purchase_price' => 0, 'stock' => 0],
            ['sku' => 'CAT-MAL-750', 'name' => 'Catalpa Malbec', 'sale_price' => 9.25, 'purchase_price' => 0, 'stock' => 0],
            ['sku' => 'CAT-MER-750', 'name' => 'Catalpa Merlot', 'sale_price' => 9.25, 'purchase_price' => 0, 'stock' => 0],
            ['sku' => 'CAT-CHA-750', 'name' => 'Catalpa chardonnay', 'sale_price' => 9.25, 'purchase_price' => 0, 'stock' => 0],
            ['sku' => 'ATA-CAB-750', 'name' => 'Atamisque Cabernet Sauvignon', 'sale_price' => 12.65, 'purchase_price' => 0, 'stock' => 0],
            ['sku' => 'ATA-MAL-750', 'name' => 'Atamisque Malbec', 'sale_price' => 12.65, 'purchase_price' => 0, 'stock' => 0],
            ['sku' => 'ATA-ASS-750', 'name' => 'Atamisque Assemblage', 'sale_price' => 17.69, 'purchase_price' => 0, 'stock' => 0],
            ['sku' => 'ATA-PET-750', 'name' => 'Atamisque Petit Verdot', 'sale_price' => 17.69, 'purchase_price' => 0, 'stock' => 0],
            ['sku' => 'ATA-CHA-750', 'name' => 'Atamisque Chardonnay', 'sale_price' => 17.69, 'purchase_price' => 0, 'stock' => 0],
            ['sku' => 'SER-SBL-750', 'name' => 'Serbal Sauvigon Blanc', 'sale_price' => 6.28, 'purchase_price' => 0, 'stock' => 0],
            ['sku' => 'SER-ROS-750', 'name' => 'Serbal Rose', 'sale_price' => 6.28, 'purchase_price' => 0, 'stock' => 0],
        ];

        foreach ($productos as $producto) {
            Product::query()->updateOrCreate(
                ['sku' => $producto['sku']],
                [
                    'name' => $producto['name'],
                    'sale_price' => $producto['sale_price'],
                    'purchase_price' => $producto['purchase_price'],
                    'stock' => $producto['stock'],
                ],
            );
        }

        $this->command->info('✅ Proveedor "Bodega Atamisque" (ID: '.$supplier->id.')');
        $this->command->info('✅ '.count($productos).' productos sincronizados desde Productos.csv');
    }
}

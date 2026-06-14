<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductosAtamisqueSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Crear o encontrar el proveedor
        $proveedorId = DB::table('suppliers')->insertGetId([
            'name' => 'Bodega Atamisque',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 2. Insertar productos
        $productos = [
            // Línea Serbal
            ['sku' => 'SER-MAL-750', 'name' => 'Serbal Malbec',           'sale_price' => 6.28,  'purchase_price' => 0, 'stock' => 0],
            ['sku' => 'SER-CAB-750', 'name' => 'Serbal Cabernet Franc',   'sale_price' => 6.28,  'purchase_price' => 0, 'stock' => 0],
            ['sku' => 'SER-PIN-750', 'name' => 'Serbal Pinot Noir',       'sale_price' => 6.28,  'purchase_price' => 0, 'stock' => 0],
            ['sku' => 'SER-CHA-750', 'name' => 'Serbal Chardonnay',       'sale_price' => 6.28,  'purchase_price' => 0, 'stock' => 0],
            // Línea Catalpa
            ['sku' => 'CAT-MAL-750', 'name' => 'Catalpa Malbec',          'sale_price' => 9.25,  'purchase_price' => 0, 'stock' => 0],
            ['sku' => 'CAT-MER-750', 'name' => 'Catalpa Merlot',          'sale_price' => 9.25,  'purchase_price' => 0, 'stock' => 0],
            ['sku' => 'CAT-CHA-750', 'name' => 'Catalpa Chardonnay',      'sale_price' => 9.25,  'purchase_price' => 0, 'stock' => 0],
            // Línea Atamisque
            ['sku' => 'ATA-CAB-750', 'name' => 'Atamisque Cabernet Sauvignon', 'sale_price' => 12.65, 'purchase_price' => 0, 'stock' => 0],
            ['sku' => 'ATA-MAL-750', 'name' => 'Atamisque Malbec',        'sale_price' => 12.65, 'purchase_price' => 0, 'stock' => 0],
            ['sku' => 'ATA-ASS-750', 'name' => 'Atamisque Assemblage',    'sale_price' => 17.69, 'purchase_price' => 0, 'stock' => 0],
            ['sku' => 'ATA-PET-750', 'name' => 'Atamisque Petit Verdot',  'sale_price' => 17.69, 'purchase_price' => 0, 'stock' => 0],
            ['sku' => 'ATA-CHA-750', 'name' => 'Atamisque Chardonnay',    'sale_price' => 17.69, 'purchase_price' => 0, 'stock' => 0],
        ];

        foreach ($productos as $producto) {
            DB::table('products')->insertOrIgnore([
                'sku' => $producto['sku'],
                'name' => $producto['name'],
                'sale_price' => $producto['sale_price'],
                'purchase_price' => $producto['purchase_price'],
                'stock' => $producto['stock'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->command->info('✅ Proveedor "Bodega Atamisque" creado (ID: '.$proveedorId.')');
        $this->command->info('✅ 12 productos insertados (se omiten duplicados por SKU)');
    }
}

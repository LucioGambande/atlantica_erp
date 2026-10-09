<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Lot;
use App\Models\Order;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\Supplier;
use App\Services\InvoiceService;
use App\Services\StockService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Escenario de demostración de trazabilidad por lotes, pensado para la
 * documentación que se presenta a Sanidad.
 *
 * Monta el circuito completo de un lote: entra con una compra, se reparte a
 * dos clientes y queda saldo en depósito. Todos los registros llevan el
 * prefijo DEMO- en su número de documento para poder identificarlos y
 * borrarlos después.
 *
 *   ./vendor/bin/sail artisan db:seed --class=TrazabilidadDemoSeeder
 */
class TrazabilidadDemoSeeder extends Seeder
{
    public const LOT_CODE = 'L-2026-0147';

    public const SECOND_LOT_CODE = 'L-2026-0151';

    public function run(): void
    {
        Invoice::skipSequenceValidation(true);

        $supplier = Supplier::firstOrCreate(
            ['name' => 'Conservas del Cantábrico, S.L.'],
            [
                'tax_id' => 'B39284710',
                'email' => 'pedidos@conservascantabrico.example',
                'phone' => '942 00 11 22',
                'address' => 'Polígono Industrial La Marisma, nave 7, 39600 Camargo (Cantabria)',
            ],
        );

        $product = Product::firstOrCreate(
            ['sku' => 'BON-250'],
            [
                'name' => 'Bonito del Norte en aceite de oliva · lata 250 g',
                'purchase_price' => 6.80,
                'sale_price' => 11.50,
                'stock' => 0,
            ],
        );

        // Dos lotes del mismo producto: es lo que demuestra que el sistema los
        // distingue y que un incidente se acota a uno solo.
        $lot = Lot::firstOrCreate(
            ['product_id' => $product->id, 'code' => self::LOT_CODE],
            ['manufactured_at' => Carbon::parse('2026-08-12')],
        );

        $secondLot = Lot::firstOrCreate(
            ['product_id' => $product->id, 'code' => self::SECOND_LOT_CODE],
            ['manufactured_at' => Carbon::parse('2026-09-03')],
        );

        $this->purchase($supplier, $product, $lot, 'DEMO-FC-2026-0458', 240, '2026-09-15');
        $this->purchase($supplier, $product, $secondLot, 'DEMO-FC-2026-0473', 180, '2026-09-29');

        // Compra en borrador, con una línea sin lote todavía: es la única en
        // la que se pueden editar líneas (las recibidas quedan bloqueadas), y
        // por eso es la que sirve para mostrar el alta de un lote nuevo.
        $this->draftPurchase($supplier, $product, 'DEMO-FC-2026-0489', 120);

        $laRula = Customer::firstOrCreate(
            ['name' => 'Restaurante La Rula, S.L.'],
            [
                'fiscal_name' => 'Restaurante La Rula, S.L.',
                'tax_id' => 'B33105582',
                'customer_type' => 'horeca',
                'email' => 'compras@larula.example',
                'address' => 'Calle Marqués de San Esteban 14, 33206 Gijón (Asturias)',
                'fiscal_address' => 'Calle Marqués de San Esteban 14, 33206 Gijón (Asturias)',
                'city' => 'Gijón',
                'postal_code' => '33206',
            ],
        );

        $marDeLeva = Customer::firstOrCreate(
            ['name' => 'Hotel Mar de Leva, S.A.'],
            [
                'fiscal_name' => 'Hotel Mar de Leva, S.A.',
                'tax_id' => 'A39771204',
                'customer_type' => 'horeca',
                'email' => 'fyb@mardeleva.example',
                'address' => 'Paseo de Pereda 42, 39004 Santander (Cantabria)',
                'fiscal_address' => 'Paseo de Pereda 42, 39004 Santander (Cantabria)',
                'city' => 'Santander',
                'postal_code' => '39004',
            ],
        );

        // El mismo lote sale hacia dos clientes distintos: ese reparto es lo
        // que hay que poder reconstruir ante una alerta sanitaria.
        $this->sale($laRula, $product, $lot, 48, '2026-09-18');
        $this->sale($marDeLeva, $product, $lot, 36, '2026-09-22');

        // Una venta del otro lote, para que se vea que el filtro los separa.
        $this->sale($laRula, $product, $secondLot, 24, '2026-10-02');
    }

    private function draftPurchase(Supplier $supplier, Product $product, string $documentNumber, int $quantity): void
    {
        if (PurchaseInvoice::query()->where('document_number', $documentNumber)->exists()) {
            return;
        }

        $purchase = PurchaseInvoice::create([
            'supplier_id' => $supplier->id,
            'document_number' => $documentNumber,
            'status' => 'draft',
            'total_amount' => round($quantity * 6.80 * 1.10, 2),
        ]);

        $purchase->purchaseInvoiceItems()->create([
            'product_id' => $product->id,
            'description' => $product->name,
            'quantity' => $quantity,
            'unit_price' => 6.80,
            'total_price' => round($quantity * 6.80, 2),
        ]);
    }

    private function purchase(
        Supplier $supplier,
        Product $product,
        Lot $lot,
        string $documentNumber,
        int $quantity,
        string $receivedAt,
    ): void {
        if (PurchaseInvoice::query()->where('document_number', $documentNumber)->exists()) {
            return;
        }

        $purchase = PurchaseInvoice::create([
            'supplier_id' => $supplier->id,
            'document_number' => $documentNumber,
            'status' => 'draft',
            'total_amount' => round($quantity * 6.80 * 1.10, 2),
            'received_at' => Carbon::parse($receivedAt),
        ]);

        $purchase->purchaseInvoiceItems()->create([
            'product_id' => $product->id,
            'lot_id' => $lot->id,
            'description' => $product->name,
            'quantity' => $quantity,
            'unit_price' => 6.80,
            'total_price' => round($quantity * 6.80, 2),
        ]);

        // Recibir la mercadería es lo que hace entrar el stock, identificado
        // por lote.
        $purchase->update(['status' => 'received']);
        app(StockService::class)->syncStockForPurchaseInvoice($purchase);
    }

    private function sale(Customer $customer, Product $product, Lot $lot, int $quantity, string $orderedAt): void
    {
        $order = Order::create([
            'customer_id' => $customer->id,
            'status' => 'pending',
            'total_amount' => round($quantity * 11.50, 2),
            'ordered_at' => Carbon::parse($orderedAt),
        ]);

        $order->orderItems()->create([
            'product_id' => $product->id,
            'lot_id' => $lot->id,
            'quantity' => $quantity,
            'unit_price' => 11.50,
            'discount_percent' => 0,
            'total_price' => round($quantity * 11.50, 2),
        ]);

        app(InvoiceService::class)->createFromOrder($order->fresh(['orderItems.product', 'customer']));
    }
}

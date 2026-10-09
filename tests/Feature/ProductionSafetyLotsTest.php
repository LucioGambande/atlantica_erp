<?php

namespace Tests\Feature;

use App\Models\Lot;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Services\StockSanitizerService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Escenarios con datos que YA existen en producción al momento de deployar.
 */
class ProductionSafetyLotsTest extends TestCase
{
    use RefreshDatabase;

    public function test_saving_a_legacy_draft_purchase_does_not_subtract_stock(): void
    {
        $product = Product::create([
            'name' => 'Queso', 'sku' => 'SKU-1',
            'purchase_price' => 5, 'sale_price' => 10, 'stock' => 100,
        ]);

        $purchase = $this->purchase($product, 'draft', 25);

        // Así queda una compra histórica después de la migración: marcada como
        // registrada, porque su mercadería ya está en products.stock.
        $purchase->update(['stock_movements_recorded' => true]);

        app(StockService::class)->syncStockForPurchaseInvoice($purchase);

        $this->assertSame(100, $product->fresh()->stock, 'Guardar una compra vieja no debe tocar el stock.');
        $this->assertSame(0, StockMovement::query()->count());
    }

    public function test_reverting_a_legacy_received_purchase_does_not_subtract_stock(): void
    {
        $product = Product::create([
            'name' => 'Queso', 'sku' => 'SKU-1',
            'purchase_price' => 5, 'sale_price' => 10, 'stock' => 100,
        ]);

        $purchase = $this->purchase($product, 'received', 25);
        $purchase->update(['stock_movements_recorded' => true]);

        // Nunca hubo movimientos de esta compra: el stock se cargó a mano.
        $purchase->update(['status' => 'draft']);
        app(StockService::class)->syncStockForPurchaseInvoice($purchase);

        $this->assertSame(100, $product->fresh()->stock, 'No se puede devolver stock que esta compra nunca sumó.');
    }

    public function test_sanitizing_stock_preserves_purchase_invoice_entries(): void
    {
        $product = Product::create([
            'name' => 'Queso', 'sku' => 'SKU-1',
            'purchase_price' => 5, 'sale_price' => 10, 'stock' => 0,
        ]);
        $lot = Lot::create(['product_id' => $product->id, 'code' => 'L-001']);

        $purchase = $this->purchase($product, 'received', 240, $lot);
        app(StockService::class)->syncStockForPurchaseInvoice($purchase);
        $this->assertSame(240, $product->fresh()->stock);

        app(StockSanitizerService::class)->sanitize();

        $this->assertSame(
            240,
            $product->fresh()->stock,
            'Sanear el stock no debe borrar la mercadería que entró por compras.',
        );
        $this->assertSame(1, StockMovement::query()->where('reference_type', 'PurchaseInvoice')->count());
    }

    public function test_receiving_and_reverting_repeatedly_keeps_stock_consistent(): void
    {
        $product = Product::create([
            'name' => 'Queso', 'sku' => 'SKU-1',
            'purchase_price' => 5, 'sale_price' => 10, 'stock' => 100,
        ]);

        $purchase = $this->purchase($product, 'received', 25);
        $service = app(StockService::class);

        foreach (range(1, 3) as $ignored) {
            $purchase->update(['status' => 'received']);
            $service->syncStockForPurchaseInvoice($purchase);
            $this->assertSame(125, $product->fresh()->stock);

            $purchase->update(['status' => 'draft']);
            $service->syncStockForPurchaseInvoice($purchase);
            $this->assertSame(100, $product->fresh()->stock);
        }
    }

    private function purchase(Product $product, string $status, int $quantity, ?Lot $lot = null): PurchaseInvoice
    {
        $supplier = Supplier::create(['name' => 'Proveedor Test']);

        $purchase = PurchaseInvoice::create([
            'supplier_id' => $supplier->id,
            'document_number' => 'FC-'.uniqid(),
            'status' => $status,
            'total_amount' => 100,
        ]);

        $purchase->purchaseInvoiceItems()->create([
            'product_id' => $product->id,
            'lot_id' => $lot?->id,
            'description' => 'Queso',
            'quantity' => $quantity,
            'unit_price' => 4,
            'total_price' => 4 * $quantity,
        ]);

        return $purchase->fresh();
    }
}

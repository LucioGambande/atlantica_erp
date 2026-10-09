<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Lot;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseInvoiceStockTest extends TestCase
{
    use RefreshDatabase;

    public function test_receiving_a_purchase_invoice_adds_stock_with_its_lot(): void
    {
        $product = $this->product(stock: 10);
        $lot = Lot::create(['product_id' => $product->id, 'code' => 'L-001']);
        $purchase = $this->purchase($product, $lot, quantity: 25, status: 'received');

        app(StockService::class)->syncStockForPurchaseInvoice($purchase);

        $this->assertSame(35, $product->fresh()->stock);

        $movement = StockMovement::query()->where('reference_type', 'PurchaseInvoice')->sole();
        $this->assertSame('in', $movement->type);
        $this->assertSame(25, $movement->quantity);
        $this->assertSame($lot->id, $movement->lot_id);
        $this->assertSame($purchase->id, (int) $movement->reference_id);

        $purchase->refresh();
        $this->assertTrue($purchase->stock_movements_recorded);
        $this->assertNotNull($purchase->received_at);
    }

    public function test_saving_a_received_purchase_again_does_not_duplicate_stock(): void
    {
        $product = $this->product(stock: 0);
        $purchase = $this->purchase($product, null, quantity: 25, status: 'received');

        $service = app(StockService::class);
        $service->syncStockForPurchaseInvoice($purchase);
        $service->syncStockForPurchaseInvoice($purchase);
        $service->syncStockForPurchaseInvoice($purchase);

        $this->assertSame(25, $product->fresh()->stock);
        $this->assertSame(1, StockMovement::query()->where('reference_type', 'PurchaseInvoice')->count());
    }

    public function test_sending_a_received_purchase_back_to_draft_returns_the_stock(): void
    {
        $product = $this->product(stock: 10);
        $purchase = $this->purchase($product, null, quantity: 25, status: 'received');

        $service = app(StockService::class);
        $service->syncStockForPurchaseInvoice($purchase);
        $this->assertSame(35, $product->fresh()->stock);

        $purchase->update(['status' => 'draft']);
        $service->syncStockForPurchaseInvoice($purchase);

        $this->assertSame(10, $product->fresh()->stock);
        $this->assertFalse($purchase->fresh()->stock_movements_recorded);

        // El log conserva la entrada y la salida compensatoria.
        $this->assertSame(2, StockMovement::query()->where('reference_type', 'PurchaseInvoice')->count());
    }

    public function test_a_draft_purchase_does_not_move_stock(): void
    {
        $product = $this->product(stock: 10);
        $purchase = $this->purchase($product, null, quantity: 25, status: 'draft');

        app(StockService::class)->syncStockForPurchaseInvoice($purchase);

        $this->assertSame(10, $product->fresh()->stock);
        $this->assertFalse($purchase->fresh()->stock_movements_recorded);
    }

    public function test_a_purchase_marked_as_not_stock_generating_leaves_stock_untouched(): void
    {
        $product = $this->product(stock: 10);
        $purchase = $this->purchase($product, null, quantity: 25, status: 'received');
        $purchase->update(['generates_stock_movement' => false]);

        app(StockService::class)->syncStockForPurchaseInvoice($purchase);

        $this->assertSame(10, $product->fresh()->stock);
        $this->assertFalse($purchase->fresh()->stock_movements_recorded);
    }

    public function test_lines_without_product_do_not_move_stock(): void
    {
        $supplier = Supplier::create(['name' => 'Proveedor Test']);

        $purchase = PurchaseInvoice::create([
            'supplier_id' => $supplier->id,
            'document_number' => 'COMPRA-SERVICIO',
            'status' => 'received',
            'total_amount' => 100,
        ]);

        $purchase->purchaseInvoiceItems()->create([
            'description' => 'Portes',
            'quantity' => 1,
            'unit_price' => 100,
            'total_price' => 100,
        ]);

        app(StockService::class)->syncStockForPurchaseInvoice($purchase);

        $this->assertSame(0, StockMovement::query()->count());
        $this->assertFalse($purchase->fresh()->stock_movements_recorded);
    }

    public function test_sale_stock_movement_carries_the_lot_of_the_line(): void
    {
        Invoice::skipSequenceValidation(true);

        $product = $this->product(stock: 50);
        $lot = Lot::create(['product_id' => $product->id, 'code' => 'L-001']);

        $customer = Customer::create([
            'name' => 'Cliente Test',
            'customer_type' => 'horeca',
        ]);

        $invoice = Invoice::create([
            'customer_id' => $customer->id,
            'invoice_number' => 'TEST-0001',
            'status' => 'issued',
            'total_amount' => 100,
            'generates_stock_movement' => true,
            'issued_at' => now(),
        ]);

        $invoice->invoiceItems()->create([
            'product_id' => $product->id,
            'lot_id' => $lot->id,
            'description' => 'Queso',
            'quantity' => 5,
            'unit_price' => 20,
            'discount_percent' => 0,
            'total_price' => 100,
        ]);

        app(StockService::class)->applyStockFromInvoice($invoice);

        $movement = StockMovement::query()->where('reference_type', 'Invoice')->sole();
        $this->assertSame('out', $movement->type);
        $this->assertSame($lot->id, $movement->lot_id);
        $this->assertSame(45, $product->fresh()->stock);
    }

    public function test_lot_stock_balance_reflects_entries_and_exits(): void
    {
        Invoice::skipSequenceValidation(true);

        $product = $this->product(stock: 0);
        $lot = Lot::create(['product_id' => $product->id, 'code' => 'L-001']);
        $purchase = $this->purchase($product, $lot, quantity: 240, status: 'received');

        app(StockService::class)->syncStockForPurchaseInvoice($purchase);
        $this->assertSame(240, $lot->stockBalance());

        $customer = Customer::create([
            'name' => 'Cliente Test',
            'customer_type' => 'horeca',
            'tax_id' => 'B12345678',
            'fiscal_address' => 'Calle Falsa 123',
        ]);

        $invoice = Invoice::create([
            'customer_id' => $customer->id,
            'invoice_number' => 'TEST-0001',
            'status' => 'issued',
            'total_amount' => 100,
            'generates_stock_movement' => true,
            'issued_at' => now(),
        ]);

        $invoice->invoiceItems()->create([
            'product_id' => $product->id,
            'lot_id' => $lot->id,
            'description' => 'Queso',
            'quantity' => 48,
            'unit_price' => 20,
            'discount_percent' => 0,
            'total_price' => 960,
        ]);

        app(StockService::class)->applyStockFromInvoice($invoice);

        $this->assertSame(192, $lot->stockBalance());
        // El saldo del lote y el del producto tienen que coincidir cuando hay
        // un solo lote en juego.
        $this->assertSame(192, $product->fresh()->stock);
    }

    private function product(int $stock): Product
    {
        return Product::create([
            'name' => 'Queso',
            'sku' => 'SKU-1',
            'purchase_price' => 5,
            'sale_price' => 10,
            'stock' => $stock,
        ]);
    }

    private function purchase(Product $product, ?Lot $lot, int $quantity, string $status): PurchaseInvoice
    {
        $supplier = Supplier::create(['name' => 'Proveedor Test']);

        $purchase = PurchaseInvoice::create([
            'supplier_id' => $supplier->id,
            'document_number' => 'COMPRA-'.uniqid(),
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

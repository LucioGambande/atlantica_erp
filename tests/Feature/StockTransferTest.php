<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\Warehouse;
use App\Services\StockService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockTransferTest extends TestCase
{
    use RefreshDatabase;

    public function test_completing_a_transfer_moves_stock_between_warehouses(): void
    {
        $origin = $this->warehouse('Barcelona');
        $destination = $this->warehouse('Madrid');
        $product = $this->product(stock: 100);
        $this->seedInitialStock($product, $origin, 100);

        $service = app(StockService::class);
        $transfer = $this->transfer($origin, $destination, 'completed');
        $transfer->stockTransferItems()->create(['product_id' => $product->id, 'quantity' => 30]);

        $service->applyStockTransfer($transfer);

        $this->assertSame(100, $product->fresh()->stock);
        $this->assertSame(70, $service->warehouseBalance($product, $origin->id));
        $this->assertSame(30, $service->warehouseBalance($product, $destination->id));
        $this->assertSame(2, StockMovement::query()->where('reference_type', 'StockTransfer')->count());
        $this->assertTrue($transfer->fresh()->stock_movements_recorded);
    }

    public function test_applying_a_completed_transfer_twice_does_not_duplicate_movements(): void
    {
        $origin = $this->warehouse('Barcelona');
        $destination = $this->warehouse('Madrid');
        $product = $this->product(stock: 100);
        $this->seedInitialStock($product, $origin, 100);

        $service = app(StockService::class);
        $transfer = $this->transfer($origin, $destination, 'completed');
        $transfer->stockTransferItems()->create(['product_id' => $product->id, 'quantity' => 30]);

        $service->syncStockForTransfer($transfer);
        $service->syncStockForTransfer($transfer);
        $service->syncStockForTransfer($transfer);

        $this->assertSame(2, StockMovement::query()->where('reference_type', 'StockTransfer')->count());
        $this->assertSame(70, $service->warehouseBalance($product, $origin->id));
        $this->assertSame(30, $service->warehouseBalance($product, $destination->id));
    }

    public function test_transfer_with_insufficient_origin_stock_throws_and_moves_nothing(): void
    {
        $origin = $this->warehouse('Barcelona');
        $destination = $this->warehouse('Madrid');
        $product = $this->product(stock: 10);
        $this->seedInitialStock($product, $origin, 10);

        $service = app(StockService::class);
        $transfer = $this->transfer($origin, $destination, 'completed');
        $transfer->stockTransferItems()->create(['product_id' => $product->id, 'quantity' => 999]);

        $this->expectException(DomainException::class);

        try {
            $service->applyStockTransfer($transfer);
        } finally {
            $this->assertSame(0, StockMovement::query()->where('reference_type', 'StockTransfer')->count());
            $this->assertFalse($transfer->fresh()->stock_movements_recorded);
        }
    }

    public function test_sending_a_completed_transfer_back_to_draft_reverses_the_stock(): void
    {
        $origin = $this->warehouse('Barcelona');
        $destination = $this->warehouse('Madrid');
        $product = $this->product(stock: 100);
        $this->seedInitialStock($product, $origin, 100);

        $service = app(StockService::class);
        $transfer = $this->transfer($origin, $destination, 'completed');
        $transfer->stockTransferItems()->create(['product_id' => $product->id, 'quantity' => 30]);
        $service->syncStockForTransfer($transfer);

        $transfer->update(['status' => 'draft']);
        $service->syncStockForTransfer($transfer);

        $this->assertSame(100, $service->warehouseBalance($product, $origin->id));
        $this->assertSame(0, $service->warehouseBalance($product, $destination->id));
        $this->assertFalse($transfer->fresh()->stock_movements_recorded);
        // El log conserva los 2 movimientos originales + los 2 compensatorios.
        $this->assertSame(4, StockMovement::query()->where('reference_type', 'StockTransfer')->count());
    }

    public function test_a_transfer_cannot_target_the_same_warehouse_as_origin_and_destination(): void
    {
        $warehouse = $this->warehouse('Barcelona');
        $product = $this->product(stock: 100);
        $this->seedInitialStock($product, $warehouse, 100);

        $transfer = StockTransfer::create([
            'origin_warehouse_id' => $warehouse->id,
            'destination_warehouse_id' => $warehouse->id,
            'status' => 'completed',
        ]);
        $transfer->stockTransferItems()->create(['product_id' => $product->id, 'quantity' => 10]);

        $this->expectException(DomainException::class);

        app(StockService::class)->applyStockTransfer($transfer);
    }

    private function seedInitialStock(Product $product, Warehouse $warehouse, int $quantity): void
    {
        $product->stockMovements()->create([
            'warehouse_id' => $warehouse->id,
            'type' => 'in',
            'quantity' => $quantity,
            'reference_type' => 'PurchaseInvoice',
            'reference_id' => 0,
        ]);
    }

    private function warehouse(string $name): Warehouse
    {
        return Warehouse::create(['name' => $name, 'type' => 'own', 'is_active' => true]);
    }

    private function product(int $stock): Product
    {
        return Product::create([
            'name' => 'Queso',
            'sku' => 'SKU-'.uniqid(),
            'purchase_price' => 5,
            'sale_price' => 10,
            'stock' => $stock,
        ]);
    }

    private function transfer(Warehouse $origin, Warehouse $destination, string $status): StockTransfer
    {
        return StockTransfer::create([
            'origin_warehouse_id' => $origin->id,
            'destination_warehouse_id' => $destination->id,
            'status' => $status,
        ]);
    }
}

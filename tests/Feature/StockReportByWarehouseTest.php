<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Warehouse;
use App\Services\StockReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockReportByWarehouseTest extends TestCase
{
    use RefreshDatabase;

    public function test_summary_scoped_to_a_warehouse_only_counts_its_movements(): void
    {
        $barcelona = Warehouse::create(['name' => 'Barcelona', 'type' => 'own', 'is_active' => true]);
        $madrid = Warehouse::create(['name' => 'Madrid', 'type' => 'own', 'is_active' => true]);
        $product = Product::create([
            'name' => 'Queso',
            'sku' => 'SKU-1',
            'purchase_price' => 5,
            'sale_price' => 10,
            'stock' => 70,
        ]);

        $product->stockMovements()->create(['warehouse_id' => $barcelona->id, 'type' => 'in', 'quantity' => 50, 'reference_type' => 'PurchaseInvoice', 'reference_id' => 0]);
        $product->stockMovements()->create(['warehouse_id' => $madrid->id, 'type' => 'in', 'quantity' => 20, 'reference_type' => 'PurchaseInvoice', 'reference_id' => 0]);

        $service = app(StockReportService::class);

        $this->assertSame(50, $service->summary($barcelona->id)['total_units']);
        $this->assertSame(20, $service->summary($madrid->id)['total_units']);
        $this->assertSame(70, $service->summary()['total_units']);
    }

    public function test_totals_by_warehouse_sum_matches_the_grand_total(): void
    {
        $barcelona = Warehouse::create(['name' => 'Barcelona', 'type' => 'own', 'is_active' => true]);
        $madrid = Warehouse::create(['name' => 'Madrid', 'type' => 'own', 'is_active' => true]);
        $product = Product::create([
            'name' => 'Queso',
            'sku' => 'SKU-1',
            'purchase_price' => 5,
            'sale_price' => 10,
            'stock' => 90,
        ]);

        $product->stockMovements()->create(['warehouse_id' => $barcelona->id, 'type' => 'in', 'quantity' => 50, 'reference_type' => 'PurchaseInvoice', 'reference_id' => 0]);
        $product->stockMovements()->create(['warehouse_id' => $madrid->id, 'type' => 'in', 'quantity' => 20, 'reference_type' => 'PurchaseInvoice', 'reference_id' => 0]);
        // Movimiento histórico sin almacén asignado.
        $product->stockMovements()->create(['warehouse_id' => null, 'type' => 'in', 'quantity' => 20, 'reference_type' => 'PurchaseInvoice', 'reference_id' => 0]);

        $totals = app(StockReportService::class)->totalsByWarehouse();

        $this->assertSame(3, $totals->count());
        $this->assertSame(90, (int) $totals->sum('total_units'));
        $this->assertTrue($totals->contains(fn ($row) => $row->warehouse_id === null));
    }
}

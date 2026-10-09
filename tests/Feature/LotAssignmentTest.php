<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Lot;
use App\Models\Order;
use App\Models\Product;
use App\Services\InvoicePrintService;
use App\Services\InvoiceService;
use App\Services\OrderPrintService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LotAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_lot_code_is_unique_per_product(): void
    {
        $queso = $this->product('Queso', 'SKU-1');
        $jamon = $this->product('Jamón', 'SKU-2');

        Lot::create(['product_id' => $queso->id, 'code' => 'L-001']);

        // El mismo código en otro producto es un lote distinto y válido.
        $otherProductLot = Lot::create(['product_id' => $jamon->id, 'code' => 'L-001']);
        $this->assertNotNull($otherProductLot->id);

        $this->expectException(QueryException::class);
        Lot::create(['product_id' => $queso->id, 'code' => 'L-001']);
    }

    public function test_invoice_created_from_order_keeps_the_lot_of_each_line(): void
    {
        Invoice::skipSequenceValidation(true);

        $product = $this->product('Queso', 'SKU-1');
        $lot = Lot::create(['product_id' => $product->id, 'code' => 'L-001']);
        $order = $this->orderWithLine($product, $lot);

        $invoice = app(InvoiceService::class)->createFromOrder($order, generatesStockMovement: false);

        $this->assertSame($lot->id, $invoice->invoiceItems->first()->lot_id);
    }

    public function test_partial_credit_note_line_inherits_the_lot(): void
    {
        Invoice::skipSequenceValidation(true);

        $product = $this->product('Queso', 'SKU-1');
        $lot = Lot::create(['product_id' => $product->id, 'code' => 'L-001']);
        $order = $this->orderWithLine($product, $lot, quantity: 10);

        $invoice = app(InvoiceService::class)->createFromOrder($order, generatesStockMovement: false);
        $item = $invoice->invoiceItems->first();

        $creditNote = app(InvoiceService::class)->createPartialCreditNote($invoice, [
            ['invoice_item_id' => $item->id, 'quantity' => 4],
        ]);

        $creditedLine = $creditNote->invoiceItems()->first();

        $this->assertSame($lot->id, $creditedLine->lot_id);
        $this->assertSame(4, $creditedLine->quantity);
    }

    public function test_printed_invoice_lines_include_the_lot_code(): void
    {
        Invoice::skipSequenceValidation(true);

        $product = $this->product('Queso', 'SKU-1');
        $lot = Lot::create(['product_id' => $product->id, 'code' => 'L-001']);
        $order = $this->orderWithLine($product, $lot);

        $invoice = app(InvoiceService::class)->createFromOrder($order, generatesStockMovement: false);

        $document = app(InvoicePrintService::class)->buildPrintData($invoice->fresh(['invoiceItems.lot']));

        $this->assertSame('L-001', $document['lines']->first()['lot']);
    }

    public function test_printed_lines_expose_a_null_lot_when_the_line_has_none(): void
    {
        Invoice::skipSequenceValidation(true);

        $product = $this->product('Queso', 'SKU-1');
        $order = $this->orderWithLine($product, null);

        $invoice = app(InvoiceService::class)->createFromOrder($order, generatesStockMovement: false);

        $document = app(InvoicePrintService::class)->buildPrintData($invoice->fresh(['invoiceItems.lot']));

        $this->assertNull($document['lines']->first()['lot']);
    }

    public function test_printed_document_shows_a_lot_column_only_when_some_line_has_a_lot(): void
    {
        Invoice::skipSequenceValidation(true);

        $product = $this->product('Queso', 'SKU-1');
        $lot = Lot::create(['product_id' => $product->id, 'code' => 'L-001']);

        $withLot = app(InvoiceService::class)
            ->createFromOrder($this->orderWithLine($product, $lot), generatesStockMovement: false);
        $withoutLot = app(InvoiceService::class)
            ->createFromOrder($this->orderWithLine($product, null), generatesStockMovement: false);

        $this->assertStringContainsString('L-001', $this->renderPdf($withLot));
        $this->assertStringContainsString('>Lote</th>', $this->renderPdf($withLot));

        // Las facturas sin lotes se siguen imprimiendo exactamente igual que antes.
        $this->assertStringNotContainsString('>Lote</th>', $this->renderPdf($withoutLot));
    }

    public function test_printed_delivery_note_shows_the_lot_of_each_line(): void
    {
        $product = $this->product('Queso', 'SKU-1');
        $lot = Lot::create(['product_id' => $product->id, 'code' => 'L-001']);
        $order = $this->orderWithLine($product, $lot);

        $service = app(OrderPrintService::class);
        $document = $service->buildPrintData($order->fresh(['customer', 'orderItems.product', 'orderItems.lot']), withPrices: false);

        $html = view('orders.pdf', ['document' => $document, 'logoBase64' => null])->render();

        $this->assertSame('L-001', $document['lines']->first()['lot']);
        $this->assertStringContainsString('>Lote</th>', $html);
        $this->assertStringContainsString('L-001', $html);
    }

    private function renderPdf(Invoice $invoice): string
    {
        $document = app(InvoicePrintService::class)
            ->buildPrintData($invoice->fresh(['customer', 'invoiceItems.product', 'invoiceItems.lot']));

        return view('invoices.pdf', ['documents' => [$document], 'logoBase64' => null])->render();
    }

    private function product(string $name, string $sku): Product
    {
        return Product::create([
            'name' => $name,
            'sku' => $sku,
            'purchase_price' => 5,
            'sale_price' => 10,
            'stock' => 100,
        ]);
    }

    private function orderWithLine(Product $product, ?Lot $lot, int $quantity = 2): Order
    {
        // Cliente individual: evita exigir datos fiscales y mantiene el test
        // enfocado en el lote, no en la numeración.
        $customer = Customer::create([
            'name' => 'Cliente Test',
            'customer_type' => 'individual',
        ]);

        $order = Order::create([
            'customer_id' => $customer->id,
            'status' => 'pending',
            'total_amount' => 0,
            'ordered_at' => now(),
        ]);

        $order->orderItems()->create([
            'product_id' => $product->id,
            'lot_id' => $lot?->id,
            'quantity' => $quantity,
            'unit_price' => 10,
            'discount_percent' => 0,
            'total_price' => 10 * $quantity,
        ]);

        return $order->fresh(['orderItems.product', 'customer']);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Product;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use RuntimeException;
use Tests\TestCase;

class MonthlyIndividualSummaryInvoiceTest extends TestCase
{
    use RefreshDatabase;

    protected function makeCustomer(string $type, string $name = 'Cliente Test'): Customer
    {
        return Customer::create([
            'name' => $name,
            'customer_type' => $type,
            'tax_id' => '12345678Z',
            'address' => 'Calle Falsa 123',
        ]);
    }

    protected function makeProduct(string $sku): Product
    {
        return Product::create([
            'name' => 'Vino '.$sku,
            'sku' => $sku,
            'purchase_price' => 5,
            'sale_price' => 10,
            'stock' => 100,
        ]);
    }

    protected function makeOrderWithItem(Customer $customer, Product $product): Order
    {
        $order = Order::create([
            'customer_id' => $customer->id,
            'status' => 'pending',
            'total_amount' => 0,
            'ordered_at' => now(),
        ]);

        $order->orderItems()->create([
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => $product->sale_price,
            'discount_percent' => 0,
            'total_price' => 2 * (float) $product->sale_price,
        ]);

        return $order->fresh(['orderItems']);
    }

    public function test_it_creates_an_individual_sale_as_a_non_fiscal_internal_invoice(): void
    {
        Invoice::skipSequenceValidation(true);

        $customer = $this->makeCustomer('individual');
        $product = $this->makeProduct('SKU-A');
        $order = $this->makeOrderWithItem($customer, $product);

        $invoice = app(InvoiceService::class)->createFromOrder($order);

        $this->assertFalse($invoice->is_fiscal_document);
        $this->assertStringStartsWith('INTERNO-', $invoice->invoice_number);
        $this->assertFalse($invoice->generates_stock_movement);

        $customer->refresh();
        $this->assertGreaterThan(0.0, (float) $customer->balance);
    }

    public function test_it_creates_a_horeca_sale_as_a_fiscal_invoice_unchanged(): void
    {
        Invoice::skipSequenceValidation(true);

        $customer = $this->makeCustomer('horeca');
        $product = $this->makeProduct('SKU-B');
        $order = $this->makeOrderWithItem($customer, $product);

        $invoice = app(InvoiceService::class)->createFromOrder($order);

        $this->assertTrue($invoice->is_fiscal_document);
        $this->assertStringStartsWith('HORECA', $invoice->invoice_number);
        $this->assertTrue($invoice->generates_stock_movement);
    }

    public function test_it_consolidates_several_individual_sales_into_one_particular_invoice(): void
    {
        Invoice::skipSequenceValidation(true);

        $consumidorFinal = $this->makeCustomer('individual', 'Consumidor Final');
        $customerA = $this->makeCustomer('individual', 'Juan');
        $customerB = $this->makeCustomer('individual', 'Pedro');
        $product = $this->makeProduct('SKU-C');

        $invoiceA = app(InvoiceService::class)->createFromOrder($this->makeOrderWithItem($customerA, $product));
        $invoiceB = app(InvoiceService::class)->createFromOrder($this->makeOrderWithItem($customerB, $product));

        $customerA->refresh();
        $customerB->refresh();
        $balanceABefore = (float) $customerA->balance;
        $balanceBBefore = (float) $customerB->balance;

        $summary = app(InvoiceService::class)->createMonthlyIndividualSummaryInvoice(
            $consumidorFinal,
            Carbon::now()->startOfMonth(),
            Carbon::now()->endOfMonth(),
        );

        $this->assertTrue($summary->is_fiscal_document);
        $this->assertStringStartsWith('PARTICULAR', $summary->invoice_number);
        $this->assertCount(2, $summary->invoiceItems);

        $consumidorFinal->refresh();
        $this->assertGreaterThan(0.0, (float) $consumidorFinal->balance);

        // Las cuentas corrientes de los clientes reales no cambian al consolidar.
        $this->assertSame($balanceABefore, (float) $customerA->fresh()->balance);
        $this->assertSame($balanceBBefore, (float) $customerB->fresh()->balance);

        $this->assertSame($summary->id, $invoiceA->fresh()->consolidated_into_invoice_id);
        $this->assertSame($summary->id, $invoiceB->fresh()->consolidated_into_invoice_id);
    }

    public function test_it_does_not_include_an_already_consolidated_sale_twice(): void
    {
        Invoice::skipSequenceValidation(true);

        $consumidorFinal = $this->makeCustomer('individual', 'Consumidor Final');
        $customer = $this->makeCustomer('individual', 'Juan');
        $product = $this->makeProduct('SKU-D');

        app(InvoiceService::class)->createFromOrder($this->makeOrderWithItem($customer, $product));

        $from = Carbon::now()->startOfMonth();
        $to = Carbon::now()->endOfMonth();

        app(InvoiceService::class)->createMonthlyIndividualSummaryInvoice($consumidorFinal, $from, $to);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No hay ventas');

        app(InvoiceService::class)->createMonthlyIndividualSummaryInvoice($consumidorFinal, $from, $to);
    }

    public function test_billing_summary_does_not_double_count_consolidated_individual_sales(): void
    {
        Invoice::skipSequenceValidation(true);

        $consumidorFinal = $this->makeCustomer('individual', 'Consumidor Final');
        $customer = $this->makeCustomer('individual', 'Juan');
        $product = $this->makeProduct('SKU-E');

        app(InvoiceService::class)->createFromOrder($this->makeOrderWithItem($customer, $product));

        $service = app(InvoiceService::class);
        $before = $service->billingSummary(null, null, null, null);

        $service->createMonthlyIndividualSummaryInvoice(
            $consumidorFinal,
            Carbon::now()->startOfMonth(),
            Carbon::now()->endOfMonth(),
        );

        $after = $service->billingSummary(null, null, null, null);

        // La venta interna nunca contó (is_fiscal_document = false); la
        // PARTICULAR mensual sí cuenta, una sola vez.
        $this->assertSame(0, $before['documents_count']);
        $this->assertSame(1, $after['documents_count']);
    }
}

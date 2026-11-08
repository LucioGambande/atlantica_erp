<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\LogisticsProvider;
use App\Models\LogisticsShipment;
use App\Services\LogisticsMarginService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogisticsMarginServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_margin_subtracts_the_actual_cost_of_linked_shipments_from_the_net_amount(): void
    {
        $customer = Customer::create(['name' => 'Cliente Test', 'customer_type' => 'horeca']);

        $invoice = Invoice::create([
            'customer_id' => $customer->id,
            'invoice_number' => 'TEST-0001',
            'status' => 'issued',
            'total_amount' => 1210, // 1000 neto + 21% IVA
            'issued_at' => now(),
        ]);

        $invoice->invoiceItems()->create([
            'description' => 'Producto',
            'quantity' => 1,
            'unit_price' => 1000,
            'discount_percent' => 0,
            'total_price' => 1000,
        ]);

        $provider = LogisticsProvider::create(['name' => 'Redypack', 'type' => 'national_carrier']);

        LogisticsShipment::create([
            'logistics_provider_id' => $provider->id,
            'invoice_id' => $invoice->id,
            'actual_cost' => 45,
            'currency' => 'EUR',
            'shipped_at' => '2026-01-10',
        ]);

        LogisticsShipment::create([
            'logistics_provider_id' => $provider->id,
            'invoice_id' => $invoice->id,
            'actual_cost' => 15,
            'currency' => 'EUR',
            'shipped_at' => '2026-01-11',
        ]);

        $result = app(LogisticsMarginService::class)->forInvoice($invoice->fresh());

        $this->assertSame(1000.0, $result['net']);
        $this->assertSame(60.0, $result['logistics_cost']);
        $this->assertSame(940.0, $result['margin']);
    }
}

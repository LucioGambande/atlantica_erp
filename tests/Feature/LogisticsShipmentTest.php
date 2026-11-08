<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\LogisticsProvider;
use App\Models\LogisticsShipment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class LogisticsShipmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_shipment_linked_to_an_invoice_resolves_its_relations(): void
    {
        $provider = $this->provider();
        $invoice = $this->invoice();

        $shipment = LogisticsShipment::create([
            'logistics_provider_id' => $provider->id,
            'invoice_id' => $invoice->id,
            'actual_cost' => 45.50,
            'currency' => 'EUR',
            'shipped_at' => '2026-01-10',
        ]);

        $this->assertSame($provider->id, $shipment->provider->id);
        $this->assertSame($invoice->id, $shipment->invoice->id);
        $this->assertSame($invoice->id, $invoice->logisticsShipments()->first()->invoice_id);
    }

    public function test_shipment_requires_at_least_one_business_link(): void
    {
        $this->expectException(RuntimeException::class);

        LogisticsShipment::create([
            'logistics_provider_id' => $this->provider()->id,
            'actual_cost' => 45.50,
            'currency' => 'EUR',
            'shipped_at' => '2026-01-10',
        ]);
    }

    private function provider(): LogisticsProvider
    {
        return LogisticsProvider::create(['name' => 'Redypack', 'type' => 'national_carrier']);
    }

    private function invoice(): Invoice
    {
        $customer = Customer::create(['name' => 'Cliente Test', 'customer_type' => 'horeca']);

        return Invoice::create([
            'customer_id' => $customer->id,
            'invoice_number' => 'TEST-0001',
            'status' => 'issued',
            'total_amount' => 100,
            'issued_at' => now(),
        ]);
    }
}

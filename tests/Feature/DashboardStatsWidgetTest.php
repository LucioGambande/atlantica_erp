<?php

namespace Tests\Feature;

use App\Filament\Widgets\DashboardStatsWidget;
use App\Models\Customer;
use App\Models\Invoice;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Number;
use Tests\TestCase;

class DashboardStatsWidgetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // El widget cachea sus métricas por mes (ver STATS_CACHE_TTL); evitamos
        // que un test lea el valor cacheado por otro.
        Cache::flush();
    }

    private function statValue(string $label): string
    {
        $widget = new DashboardStatsWidget;

        $getStats = new \ReflectionMethod($widget, 'getStats');
        $getStats->setAccessible(true);

        foreach ($getStats->invoke($widget) as $stat) {
            $labelProperty = new \ReflectionProperty($stat, 'label');
            $labelProperty->setAccessible(true);

            if ($labelProperty->getValue($stat) === $label) {
                $valueProperty = new \ReflectionProperty($stat, 'value');
                $valueProperty->setAccessible(true);

                return $valueProperty->getValue($stat);
            }
        }

        throw new \RuntimeException("Stat \"{$label}\" no encontrado.");
    }

    public function test_facturado_este_anio_resta_las_notas_de_credito(): void
    {
        Invoice::skipSequenceValidation(true);

        $customer = Customer::create([
            'name' => 'Cliente Dashboard Test',
            'customer_type' => 'horeca',
        ]);

        $invoice = Invoice::create([
            'customer_id' => $customer->id,
            'invoice_number' => 'DASH-0001',
            'status' => 'issued',
            'total_amount' => 0,
            'issued_at' => now(),
        ]);

        $invoice->invoiceItems()->create([
            'description' => 'Producto de prueba',
            'quantity' => 6,
            'unit_price' => 10,
            'discount_percent' => 0,
            'total_price' => 60,
        ]);

        $invoice->recalculateTotalFromItems();

        // Factura neta: 60 + 21% IVA = 72.6
        $this->assertSame(Number::currency(72.6, 'EUR'), $this->statValue('Facturado este año'));

        // Al cancelarla se genera una nota de crédito por el mismo importe:
        // "Facturado este año" debería volver a 0, no duplicarse a 145.2.
        app(InvoiceService::class)->cancelInvoice($invoice->fresh());
        Cache::flush();

        $this->assertSame(Number::currency(0, 'EUR'), $this->statValue('Facturado este año'));
    }
}

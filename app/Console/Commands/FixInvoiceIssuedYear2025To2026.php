<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixInvoiceIssuedYear2025To2026 extends Command
{
    protected $signature = 'invoices:fix-issued-year-2025-2026';

    protected $description = 'Corrige el año de emisión de HORECA2025-00032 a 00039: el import histórico les puso año 2025 en issued_at, pero en realidad son de 2026. Comando de un solo uso.';

    /**
     * Orden descendente: al corregir de la más nueva a la más vieja, cada
     * guardado ya encuentra a su vecino "siguiente" en la secuencia
     * corregido, evitando que InvoiceSequenceValidator bloquee un estado
     * intermedio inconsistente.
     */
    private const INVOICE_NUMBERS = [
        'HORECA2025-00039',
        'HORECA2025-00034',
        'HORECA2025-00033',
        'HORECA2025-00032',
    ];

    public function handle(): int
    {
        $invoices = Invoice::query()
            ->whereIn('invoice_number', self::INVOICE_NUMBERS)
            ->get()
            ->keyBy('invoice_number');

        foreach (self::INVOICE_NUMBERS as $number) {
            if (! $invoices->has($number)) {
                $this->error("No se encontró la factura {$number}. Se aborta sin aplicar cambios.");

                return self::FAILURE;
            }
        }

        DB::transaction(function () use ($invoices): void {
            foreach (self::INVOICE_NUMBERS as $number) {
                $invoice = $invoices->get($number);

                if ($invoice->issued_at->year !== 2025) {
                    $this->warn("{$number}: ya tiene año {$invoice->issued_at->year}, se omite.");

                    continue;
                }

                $old = $invoice->issued_at->toDateTimeString();
                $invoice->issued_at = $invoice->issued_at->copy()->addYear();
                $invoice->save();

                $this->info("{$number}: {$old} -> {$invoice->fresh()->issued_at}");
            }
        });

        $this->info('Listo. Las facturas y sus asientos en el libro mayor quedaron sincronizados.');

        return self::SUCCESS;
    }
}

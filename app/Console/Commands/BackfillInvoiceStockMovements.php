<?php

namespace App\Console\Commands;

use App\Services\StockSanitizerService;
use Illuminate\Console\Command;

class BackfillInvoiceStockMovements extends Command
{
    protected $signature = 'stock:backfill-invoice-movements {--dry-run : Simular sin escribir en la base de datos}';

    protected $description = 'Genera los movimientos de stock faltantes de facturas emitidas/pagadas que nunca los tuvieron, fechados con el issued_at real de cada factura';

    public function handle(StockSanitizerService $sanitizer): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('Modo simulación — no se escribirá en la base de datos.');
        }

        $this->info('Buscando facturas emitidas/pagadas sin movimientos de stock propios...');

        $stats = $sanitizer->backfillMissingInvoiceMovements($dryRun);

        $this->table(
            ['Métrica', 'Cantidad'],
            [
                ['Facturas encontradas sin movimientos', $stats['invoices_found']],
                ['Facturas procesadas', $stats['invoices_processed']],
                ['Movimientos de stock creados', $stats['movements_created']],
                ['Omitidas (cliente individual, ya movió stock por el pedido)', $stats['skipped_individual']],
            ],
        );

        if ($dryRun) {
            $this->comment('Ejecutá sin --dry-run para aplicar los cambios.');
        } else {
            $this->info('Movimientos generados, fechados con el issued_at real de cada factura.');
        }

        return self::SUCCESS;
    }
}

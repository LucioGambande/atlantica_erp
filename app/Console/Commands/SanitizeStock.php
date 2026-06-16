<?php

namespace App\Console\Commands;

use App\Services\StockSanitizerService;
use Illuminate\Console\Command;

class SanitizeStock extends Command
{
    protected $signature = 'stock:sanitize {--dry-run : Simular sin escribir en la base de datos}';

    protected $description = 'Alinea movimientos de stock con facturas emitidas que tocan stock';

    public function handle(StockSanitizerService $sanitizer): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('Modo simulación — no se escribirá en la base de datos.');
        }

        $this->info('Sanitizando stock…');

        $stats = $sanitizer->sanitize($dryRun);

        $this->table(
            ['Métrica', 'Cantidad'],
            [
                ['Facturas marcadas para tocar stock', $stats['invoices_flagged']],
                ['Movimientos eliminados (huérfanos / no factura)', $stats['movements_removed']],
                ['Movimientos creados desde facturas', $stats['movements_created']],
                ['Productos con stock recalculado', $stats['products_recalculated']],
            ],
        );

        if ($dryRun) {
            $this->comment('Ejecutá sin --dry-run para aplicar los cambios.');
        } else {
            $this->info('Stock sanitizado correctamente.');
        }

        return self::SUCCESS;
    }
}

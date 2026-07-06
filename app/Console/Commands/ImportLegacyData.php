<?php

namespace App\Console\Commands;

use App\Services\LegacyDataImporter;
use Illuminate\Console\Command;
use Throwable;

class ImportLegacyData extends Command
{
    protected $signature = 'import:legacy-data
        {--dry-run : Simula la importación sin escribir en la base de datos}
        {--reset-invoices : Borra facturas, líneas, pagos y ledger antes de importar}
        {--invoices-only : Solo importa facturas, líneas y pagos (no toca clientes)}';

    protected $description = 'Importa clientes, facturas, líneas y pagos históricos desde CSVs en la raíz del proyecto';

    public function handle(LegacyDataImporter $importer): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $resetInvoices = (bool) $this->option('reset-invoices');
        $invoicesOnly = (bool) $this->option('invoices-only');

        if ($dryRun) {
            $this->warn('Modo dry-run: no se escribirá nada en la base de datos.');
        }

        if ($resetInvoices && $dryRun) {
            $this->warn('--reset-invoices se ignora en dry-run.');
        }

        if ($resetInvoices && ! $dryRun) {
            $this->warn('Borrando facturas, líneas, pagos y movimientos de cuenta corriente...');
            $importer->resetInvoiceData();
            $this->info('Tablas de facturación vaciadas.');
        }

        $this->info('Archivos esperados en la raíz del proyecto: clientes.csv, facturas.csv, lineas_factura.csv (pagos.csv opcional)');

        try {
            $importer->import($dryRun, $invoicesOnly);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $summary = $importer->summary();

        $this->newLine();
        $this->info($dryRun ? 'Resumen dry-run' : 'Importación completada');
        $this->table(
            ['Entidad', 'Creados', 'Actualizados', 'Omitidos'],
            [
                ['Clientes', $summary['customers']['created'], $summary['customers']['updated'], $summary['customers']['skipped']],
                ['Facturas', $summary['invoices']['created'], $summary['invoices']['updated'], $summary['invoices']['skipped']],
                ['Líneas', $summary['lines']['created'], $summary['lines']['updated'], $summary['lines']['skipped']],
                ['Productos (catálogo)', $summary['products']['created'], $summary['products']['existing'], '—'],
                ['Pagos', $summary['payments']['created'], $summary['payments']['updated'], $summary['payments']['skipped']],
            ],
        );

        $this->printSkipped('Facturas omitidas', $summary['skipped_invoices']);
        $this->printSkipped('Líneas omitidas', $summary['skipped_lines']);
        $this->printSkipped('Pagos omitidos', $summary['skipped_payments']);

        $comparison = $summary['comparison'];
        $this->newLine();
        $this->info('Comparación CSV origen vs base de datos (para validar contra Excel)');
        $this->table(
            ['Métrica', 'CSV origen', 'Base de datos'],
            [
                ['Clientes (cantidad)', $comparison['customers']['csv'], $comparison['customers']['db']],
                ['Facturas (cantidad)', $comparison['invoices']['csv'], $comparison['invoices']['db']],
                ['Suma total_factura / total_amount', number_format($comparison['totals']['csv'], 2, ',', '.').' EUR', number_format($comparison['totals']['db'], 2, ',', '.').' EUR'],
            ],
        );

        if ($dryRun) {
            $this->comment('Ejecutá sin --dry-run para persistir los cambios.');
        } else {
            $this->comment('Ledger de cuentas corrientes reconstruido para todos los clientes.');
        }

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $messages
     */
    protected function printSkipped(string $title, array $messages): void
    {
        if ($messages === []) {
            return;
        }

        $this->newLine();
        $this->warn($title.':');

        foreach ($messages as $message) {
            $this->line('  - '.$message);
        }
    }
}

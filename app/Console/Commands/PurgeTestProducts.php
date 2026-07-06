<?php

namespace App\Console\Commands;

use App\Services\TestProductPurgeService;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;

class PurgeTestProducts extends Command
{
    use ConfirmableTrait;

    protected $signature = 'products:purge-test
        {--prefix=WINE : Prefijo de SKU a eliminar (p. ej. WINE o WIINE)}
        {--dry-run : Simula sin escribir en la base de datos}
        {--force : Ejecutar en producción sin confirmación interactiva}';

    protected $description = 'Elimina productos de prueba por prefijo de SKU y sus relaciones (líneas, pedidos, stock, listas de precio)';

    public function handle(TestProductPurgeService $service): int
    {
        $prefix = (string) $this->option('prefix');
        $dryRun = (bool) $this->option('dry-run');

        if ($prefix === '') {
            $this->error('El prefijo no puede estar vacío.');

            return self::FAILURE;
        }

        $preview = $service->preview($prefix);

        if ($preview['products'] === 0) {
            $this->warn("No hay productos con SKU que empiece por \"{$prefix}\".");

            return self::SUCCESS;
        }

        $this->info("Productos encontrados con prefijo \"{$prefix}\":");
        $this->table(
            ['SKU', 'Nombre'],
            collect($preview['products_list'])->map(fn (array $product): array => [
                $product['sku'],
                $product['name'],
            ]),
        );

        $this->newLine();
        $this->info('Relaciones que se eliminarán o actualizarán:');
        $this->table(
            ['Relación', 'Registros'],
            [
                ['Líneas de factura', $preview['invoice_items']],
                ['Líneas de pedido', $preview['order_items']],
                ['Movimientos de stock', $preview['stock_movements']],
                ['Líneas de lista de precio', $preview['price_list_items']],
                ['Líneas de factura de compra', $preview['purchase_invoice_items']],
            ],
        );

        if ($dryRun) {
            $this->newLine();
            $this->comment('Modo simulación: no se escribió nada en la base de datos.');
            $this->comment('Ejecutá sin --dry-run para aplicar los cambios.');

            return self::SUCCESS;
        }

        if (! $this->confirmToProceed()) {
            $this->warn('Operación cancelada.');

            return self::FAILURE;
        }

        $stats = $service->purge($prefix, dryRun: false);

        $this->newLine();
        $this->info('Limpieza completada:');
        $this->table(
            ['Acción', 'Cantidad'],
            [
                ['Productos eliminados', $stats['products_deleted']],
                ['Líneas de factura eliminadas', $stats['invoice_items_deleted']],
                ['Facturas recalculadas', $stats['invoices_recalculated']],
                ['Facturas vacías eliminadas', $stats['invoices_deleted']],
                ['Líneas de pedido eliminadas', $stats['order_items_deleted']],
                ['Pedidos vacíos eliminados', $stats['orders_deleted']],
                ['Movimientos de stock eliminados', $stats['stock_movements_deleted']],
                ['Líneas de lista de precio eliminadas', $stats['price_list_items_deleted']],
                ['Líneas de factura de compra eliminadas', $stats['purchase_invoice_items_deleted']],
                ['Cuentas corrientes reconstruidas', $stats['customers_rebuilt']],
            ],
        );

        return self::SUCCESS;
    }
}

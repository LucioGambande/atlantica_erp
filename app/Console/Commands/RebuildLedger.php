<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Services\AccountStatementService;
use Illuminate\Console\Command;

class RebuildLedger extends Command
{
    protected $signature = 'ledger:rebuild {customer_id? : ID del cliente (opcional)} {--force : Reconstruir sin confirmación}';

    protected $description = 'Reconstruye el libro mayor y el saldo de cuenta corriente desde facturas (con IVA) y pagos existentes';

    public function handle(AccountStatementService $accountStatementService): int
    {
        $customerId = $this->argument('customer_id');

        if ($customerId !== null) {
            $customer = Customer::query()->find($customerId);

            if ($customer === null) {
                $this->error("Cliente #{$customerId} no encontrado.");

                return self::FAILURE;
            }

            $accountStatementService->rebuildLedger($customer);
            $customer->refresh();

            $this->info("Ledger reconstruido para {$customer->name} (ID {$customer->id}). Saldo: €{$customer->balance}");

            return self::SUCCESS;
        }

        $count = Customer::query()->count();

        if ($count === 0) {
            $this->warn('No hay clientes en la base de datos.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm("¿Reconstruir el libro mayor de {$count} clientes? Los importes de factura se recalcularán con IVA.", true)) {
            $this->warn('Operación cancelada.');

            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        Customer::query()
            ->orderBy('id')
            ->chunkById(50, function ($customers) use ($accountStatementService, $bar): void {
                foreach ($customers as $customer) {
                    $accountStatementService->rebuildLedger($customer);
                    $bar->advance();
                }
            });

        $bar->finish();
        $this->newLine(2);
        $this->info("Ledger reconstruido para {$count} clientes.");

        return self::SUCCESS;
    }
}

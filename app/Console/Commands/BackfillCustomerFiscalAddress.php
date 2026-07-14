<?php

namespace App\Console\Commands;

use App\Models\Customer;
use Illuminate\Console\Command;

class BackfillCustomerFiscalAddress extends Command
{
    protected $signature = 'customers:backfill-fiscal-address
        {--dry-run : Simula sin escribir en la base de datos}';

    protected $description = 'Copia address a fiscal_address solo cuando fiscal_address está vacío';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('Modo simulación — no se escribirá en la base de datos.');
        }

        $query = Customer::query()
            ->where(function ($q): void {
                $q->whereNull('fiscal_address')
                    ->orWhere('fiscal_address', '');
            })
            ->whereNotNull('address')
            ->where('address', '!=', '');

        $total = (clone $query)->count();

        if ($total === 0) {
            $this->info('No hay clientes pendientes de completar fiscal_address.');

            return self::SUCCESS;
        }

        $updated = 0;

        $query->orderBy('id')->chunkById(100, function ($customers) use ($dryRun, &$updated): void {
            foreach ($customers as $customer) {
                if (filled($customer->fiscal_address)) {
                    continue;
                }

                if ($dryRun) {
                    $this->line("Simular #{$customer->id} {$customer->name}: fiscal_address ← address");
                    $updated++;

                    continue;
                }

                $customer->update(['fiscal_address' => $customer->address]);
                $updated++;
            }
        });

        $this->info($dryRun
            ? "Simulación: {$updated} cliente(s) se actualizarían de {$total} candidato(s)."
            : "Actualizados {$updated} cliente(s) de {$total} candidato(s).");

        return self::SUCCESS;
    }
}

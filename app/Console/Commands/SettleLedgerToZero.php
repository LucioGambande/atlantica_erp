<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\Invoice;
use App\Services\AccountStatementService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SettleLedgerToZero extends Command
{
    protected $signature = 'ledger:settle-to-zero
        {customer_id : ID del cliente}
        {description : Motivo del ajuste (se guarda tal cual en el libro mayor)}
        {--invoice_id= : Factura a la que queda asociado el ajuste (por defecto, la última emitida)}
        {--force : No pedir confirmación}';

    protected $description = 'Carga un ajuste manual en la cuenta corriente de un cliente que deja el saldo en 0. '
        .'Uso excepcional para condonaciones/acuerdos comerciales puntuales, nunca para corregir errores de cálculo.';

    public function handle(AccountStatementService $accountStatementService): int
    {
        $customer = Customer::query()->find($this->argument('customer_id'));

        if ($customer === null) {
            $this->error("Cliente #{$this->argument('customer_id')} no encontrado.");

            return self::FAILURE;
        }

        $balance = round((float) $customer->balance, 2);

        if (abs($balance) < 0.005) {
            $this->warn("El saldo de {$customer->name} ya está en 0. No se registra ningún ajuste.");

            return self::SUCCESS;
        }

        $invoiceId = $this->option('invoice_id');
        $reference = $invoiceId !== null
            ? Invoice::query()->find($invoiceId)
            : $customer->invoices()->whereNotNull('issued_at')->orderByDesc('issued_at')->first();

        if ($reference === null) {
            $this->error('No se encontró ninguna factura de referencia para asociar el ajuste.');

            return self::FAILURE;
        }

        $description = $this->argument('description');

        $this->info("Cliente: {$customer->name} (ID {$customer->id})");
        $this->info('Saldo actual: €'.number_format($balance, 2, ',', '.'));
        $this->info('Factura de referencia: '.$reference->invoice_number);
        $this->info('Motivo: '.$description);

        if (! $this->option('force') && ! $this->confirm('¿Registrar el ajuste que deja el saldo en 0?', false)) {
            $this->warn('Operación cancelada.');

            return self::SUCCESS;
        }

        // balance > 0 (el cliente debe) -> hay que acreditarlo (credit).
        // balance < 0 (a favor del cliente) -> hay que debitarlo (debit).
        $debit = $balance < 0 ? abs($balance) : 0.0;
        $credit = $balance > 0 ? $balance : 0.0;

        $entry = $accountStatementService->registerAdjustment(
            customer: $customer,
            reference: $reference,
            description: $description,
            debit: $debit,
            credit: $credit,
            date: Carbon::now(),
        );

        if ($entry === null) {
            $this->error('No se pudo registrar el ajuste.');

            return self::FAILURE;
        }

        $customer->refresh();
        $this->info('Ajuste registrado (entry #'.$entry->id.'). Nuevo saldo: €'.number_format((float) $customer->balance, 2, ',', '.'));

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Services\HubSpotInteractionImportService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class ImportHubSpotInteractionsCommand extends Command
{
    protected $signature = 'hubspot:import-interactions
        {--customer= : ID de un cliente puntual (por defecto, todos los que tengan hubspot_company_id)}
        {--types=calls,meetings,emails : Tipos de HubSpot a importar, separados por coma}
        {--since= : Fecha mínima Y-m-d; no importa engagements anteriores}
        {--dry-run : No escribe nada, solo muestra qué importaría}';

    protected $description = 'Importa el histórico de llamadas, visitas (meetings) y mails de HubSpot como CustomerInteraction.';

    protected const ALLOWED_TYPES = ['calls', 'meetings', 'emails'];

    public function handle(HubSpotInteractionImportService $service): int
    {
        $types = array_values(array_intersect(
            self::ALLOWED_TYPES,
            array_map('trim', explode(',', (string) $this->option('types'))),
        ));

        if ($types === []) {
            $this->error('Ningún tipo válido. Usá: '.implode(', ', self::ALLOWED_TYPES));

            return self::FAILURE;
        }

        $since = $this->option('since') ? CarbonImmutable::parse((string) $this->option('since')) : null;
        $dryRun = (bool) $this->option('dry-run');

        $query = Customer::query()->whereNotNull('hubspot_company_id');

        if ($customerId = $this->option('customer')) {
            $query->where('id', $customerId);
        }

        $customers = $query->get();

        if ($customers->isEmpty()) {
            $this->warn('No hay clientes con hubspot_company_id.');

            return self::SUCCESS;
        }

        $this->info(($dryRun ? '[DRY-RUN] ' : '').'Importando '.implode(', ', $types)." para {$customers->count()} clientes...");

        $bar = $this->output->createProgressBar($customers->count());
        $totals = ['fetched' => 0, 'imported' => 0, 'skipped' => 0];

        foreach ($customers as $customer) {
            $stats = $service->importForCustomer($customer, $types, $since, $dryRun);

            $totals['fetched'] += $stats['fetched'];
            $totals['imported'] += $stats['imported'];
            $totals['skipped'] += $stats['skipped'];

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->table(
            ['Encontrados', 'Importados', 'Omitidos'],
            [[$totals['fetched'], $totals['imported'], $totals['skipped']]],
        );

        if ($dryRun) {
            $this->comment('Dry-run: no se escribió nada. Corré sin --dry-run para importar de verdad.');
        }

        return self::SUCCESS;
    }
}

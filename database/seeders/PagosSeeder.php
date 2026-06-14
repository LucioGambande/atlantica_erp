<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PagosSeeder extends Seeder
{
    public function run(): void
    {
        // Obtener IDs de métodos de pago por slug
        $transferencia = DB::table('payment_methods')->where('slug', 'transferencia')->value('id');
        $efectivo      = DB::table('payment_methods')->where('slug', 'efectivo')->value('id');
        $bizum         = DB::table('payment_methods')->where('slug', 'bizum')->value('id');

        if (!$transferencia || !$efectivo || !$bizum) {
            $this->command->error('❌ Faltan métodos de pago. Correr primero: db:seed --class=PaymentMethodSeeder');
            return;
        }

        // Helper para obtener invoice_id por número de factura
        $inv = fn(string $num) => DB::table('invoices')->where('invoice_number', $num)->value('id');

        // Helper para obtener customer_id por hubspot_id
        $cus = fn(string $hid) => DB::table('customers')->where('hubspot_company_id', $hid)->value('id');

        $pagos = [
            // FAC 2 — Las Tablas del Rey — transferencia 24/10/2025
            [
                'invoice_number' => 'HORECA2025-00002',
                'hubspot_id'     => '423549031616',
                'amount'         => 225.50,
                'paid_at'        => '2025-10-24',
                'method_id'      => $transferencia,
                'notes'          => 'Alimentos de Las Pampas SL - Fact:202500002',
            ],
            // FAC 3 — La Sureña Mijas — transferencia 28/02/2026 (Griselda Bogado)
            [
                'invoice_number' => 'HORECA2025-00003',
                'hubspot_id'     => '425826400474',
                'amount'         => 86.36,
                'paid_at'        => '2026-02-28',
                'method_id'      => $transferencia,
                'notes'          => 'Griselda Carolina Bogado Vazquez',
            ],
            // FAC 4 — La Sureña Huelin — transferencia (María Belén 17/11/2025)
            [
                'invoice_number' => 'HORECA2025-00004',
                'hubspot_id'     => '424730192109',
                'amount'         => 67.57,
                'paid_at'        => '2025-11-17',
                'method_id'      => $transferencia,
                'notes'          => 'Maria Belen Hernandez Cimarosti',
            ],
            // FAC 8 — ALONSO — transferencia 30/10/2025
            [
                'invoice_number' => 'HORECA2025-00008',
                'hubspot_id'     => '429008446682',
                'amount'         => 67.16,
                'paid_at'        => '2025-10-30',
                'method_id'      => $transferencia,
                'notes'          => 'Alonso Mediadores SL - Vino gestiones comerciales',
            ],
            // FAC 9 — Las Tablas del Rey — transferencia 24/10/2025 (mismo pago cubre fac 2 y 9 aprox)
            [
                'invoice_number' => 'HORECA2025-00009',
                'hubspot_id'     => '423549031616',
                'amount'         => 158.34,
                'paid_at'        => '2025-10-24',
                'method_id'      => $transferencia,
                'notes'          => 'Alimentos de Las Pampas SL - Fact:202500002',
            ],
            // FAC 13 — Bar Atuel — transferencia 17/11/2025
            [
                'invoice_number' => 'HORECA2025-00013',
                'hubspot_id'     => '415150265590',
                'amount'         => 158.99,
                'paid_at'        => '2025-11-17',
                'method_id'      => $transferencia,
                'notes'          => 'Roberto Carlos Gutierrez - Pago Factura',
            ],
            // FAC 14 — Bar Atuel — transferencia 03/12/2025
            [
                'invoice_number' => 'HORECA2025-00014',
                'hubspot_id'     => '415150265590',
                'amount'         => 67.16,
                'paid_at'        => '2025-12-03',
                'method_id'      => $transferencia,
                'notes'          => 'Roberto Carlos Gutierrez - Pago Factura',
            ],
            // FAC 15 — KACHAFAZ — transferencia 05/12/2025 (marcada paid total)
            [
                'invoice_number' => 'HORECA2025-00015',
                'hubspot_id'     => '429007730890',
                'amount'         => 592.63,
                'paid_at'        => '2025-12-05',
                'method_id'      => $transferencia,
                'notes'          => 'Kachafaz SL - 2025-25',
            ],
            // FAC 16 — ALONSO — transferencia 27/11/2025
            [
                'invoice_number' => 'HORECA2025-00016',
                'hubspot_id'     => '429008446682',
                'amount'         => 201.47,
                'paid_at'        => '2025-11-27',
                'method_id'      => $transferencia,
                'notes'          => 'Alonso Mediadores SL - Vino gestiones comerciales',
            ],
            // FAC 20 — Bar Atuel — transferencia 29/12/2025
            [
                'invoice_number' => 'HORECA2025-00020',
                'hubspot_id'     => '415150265590',
                'amount'         => 158.99,
                'paid_at'        => '2025-12-29',
                'method_id'      => $transferencia,
                'notes'          => 'Roberto Carlos Gutierrez - Pago Factura',
            ],
            // FAC 22 — CHEMO — transferencia 27/02/2026 (marcada paid total)
            [
                'invoice_number' => 'HORECA2025-00022',
                'hubspot_id'     => '428840830178',
                'amount'         => 5530.31,
                'paid_at'        => '2026-02-27',
                'method_id'      => $transferencia,
                'notes'          => 'Chemo Iberica SA - 45754634-2025-00022',
            ],
            // FAC 24 — Bar Atuel — transferencia 29/12/2025
            [
                'invoice_number' => 'HORECA2025-00024',
                'hubspot_id'     => '415150265590',
                'amount'         => 223.28,
                'paid_at'        => '2025-12-29',
                'method_id'      => $transferencia,
                'notes'          => 'Roberto Carlos Gutierrez - Pago Factura',
            ],
            // FAC 29 — EL OMBU — transferencia 27/02/2026
            [
                'invoice_number' => 'HORECA2026-00029',
                'hubspot_id'     => '428259928300',
                'amount'         => 362.27,
                'paid_at'        => '2026-02-27',
                'method_id'      => $transferencia,
                'notes'          => 'El Ombu Empanadas SL - Bodega Atamisque',
            ],
            // FAC 34 — MAXIMARKET — efectivo
            [
                'invoice_number' => 'HORECA2025-00034',
                'hubspot_id'     => '424539151597',
                'amount'         => 182.37,
                'paid_at'        => '2025-02-27',
                'method_id'      => $efectivo,
                'notes'          => 'Efectivo',
            ],
            // FAC 35 — Bar Atuel — 2 transferencias: 09/03 + 16/03/2026
            [
                'invoice_number' => 'HORECA2026-00035',
                'hubspot_id'     => '415150265590',
                'amount'         => 100.00,
                'paid_at'        => '2026-03-09',
                'method_id'      => $transferencia,
                'notes'          => 'Roberto Carlos Gutierrez - Pago Factura (parcial 1)',
            ],
            [
                'invoice_number' => 'HORECA2026-00035',
                'hubspot_id'     => '415150265590',
                'amount'         => 93.74,
                'paid_at'        => '2026-03-16',
                'method_id'      => $transferencia,
                'notes'          => 'Roberto Carlos Gutierrez - Pago Factura (parcial 2)',
            ],
            // FAC 36 — Tinajas Tierra — efectivo
            [
                'invoice_number' => 'HORECA2026-00036',
                'hubspot_id'     => '420189971690',
                'amount'         => 112.75,
                'paid_at'        => '2026-03-10',
                'method_id'      => $efectivo,
                'notes'          => 'Efectivo',
            ],
            // FAC 39 — KALIDAD — efectivo
            [
                'invoice_number' => 'HORECA2025-00039',
                'hubspot_id'     => '428801234142',
                'amount'         => 250.83,
                'paid_at'        => '2025-03-20',
                'method_id'      => $efectivo,
                'notes'          => 'Efectivo',
            ],
            // FAC 40 — Boc and Roll — transferencia 09/04/2026
            [
                'invoice_number' => 'HORECA2026-00040',
                'hubspot_id'     => '416584819948',
                'amount'         => 112.75,
                'paid_at'        => '2026-04-09',
                'method_id'      => $transferencia,
                'notes'          => 'Boc And Roll MCM SL - Factura',
            ],
            // FAC 41 — El Club de las Brasas — transferencia 24/03/2026
            [
                'invoice_number' => 'HORECA2026-00041',
                'hubspot_id'     => '422773001417',
                'amount'         => 128.68,
                'paid_at'        => '2026-03-24',
                'method_id'      => $transferencia,
                'notes'          => 'Sebastian Rodrigo Martini - Fac41',
            ],
            // FAC 42 — NEXO GLOBAL EPDE — bizum
            [
                'invoice_number' => 'HORECA2026-00042',
                'hubspot_id'     => '422731248843',
                'amount'         => 75.99,
                'paid_at'        => '2026-03-20',
                'method_id'      => $bizum,
                'notes'          => 'Bizum',
            ],
            // FAC 47 — MAXIMARKET — efectivo
            [
                'invoice_number' => 'HORECA2026-00047',
                'hubspot_id'     => '424539151597',
                'amount'         => 182.37,
                'paid_at'        => '2026-03-31',
                'method_id'      => $efectivo,
                'notes'          => 'Efectivo',
            ],
            // FAC 53 — Malbec Angus Place — transferencia 04/04/2026
            [
                'invoice_number' => 'HORECA2026-00053',
                'hubspot_id'     => '422575950038',
                'amount'         => 45.59,
                'paid_at'        => '2026-04-04',
                'method_id'      => $transferencia,
                'notes'          => 'Colaneri Hernan - Fact 2025-00053',
            ],
            // FAC 55 — El Club de las Brasas — bizum
            [
                'invoice_number' => 'HORECA2026-00055',
                'hubspot_id'     => '422773001417',
                'amount'         => 45.59,
                'paid_at'        => '2026-04-25',
                'method_id'      => $bizum,
                'notes'          => 'Bizum',
            ],
        ];

        $inserted = 0;
        $skipped  = 0;

        foreach ($pagos as $pago) {
            $invoiceId  = $inv($pago['invoice_number']);
            $customerId = $cus($pago['hubspot_id']);

            if (!$invoiceId) {
                $this->command->warn("⚠️  Factura {$pago['invoice_number']} no encontrada — omitida");
                $skipped++;
                continue;
            }

            if (!$customerId) {
                $this->command->warn("⚠️  Cliente hubspot={$pago['hubspot_id']} no encontrado — omitida");
                $skipped++;
                continue;
            }

            // Crear detalle genérico (cash/bizum/transfer sin campos extra obligatorios)
            $detailId = DB::table('generic_payment_details')->insertGetId([
                'notes'      => $pago['notes'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('payments')->insert([
                'customer_id'       => $customerId,
                'invoice_id'        => $invoiceId,
                'payment_method_id' => $pago['method_id'],
                'detail_type'       => 'generic',
                'detail_id'         => $detailId,
                'amount'            => $pago['amount'],
                'paid_at'           => $pago['paid_at'],
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);

            // Marcar factura como paid si la suma de pagos cubre el total
            $totalPagado = DB::table('payments')
                ->where('invoice_id', $invoiceId)
                ->sum('amount');

            $totalFactura = DB::table('invoices')
                ->where('id', $invoiceId)
                ->value('total_amount');

            if ($totalPagado >= $totalFactura) {
                DB::table('invoices')
                    ->where('id', $invoiceId)
                    ->update(['status' => 'paid', 'updated_at' => now()]);
            }

            $inserted++;
            $this->command->info("✅ Pago {$pago['invoice_number']} | €{$pago['amount']} | {$pago['paid_at']}");
        }

        $this->command->info('---');
        $this->command->info("Total insertados: {$inserted} | Omitidos: {$skipped}");
    }
}

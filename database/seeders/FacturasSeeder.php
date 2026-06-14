<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FacturasSeeder extends Seeder
{
    public function run(): void
    {
        // Mapa hubspot_id => customer_id en Laravel
        // El seeder busca dinámicamente en la tabla customers
        $hubspotMap = $this->buildHubspotMap();

        $invoices = $this->getInvoices();
        $lines = $this->getLines();

        $linesByInvoice = [];
        foreach ($lines as $line) {
            $linesByInvoice[$line['factura_id']][] = $line;
        }

        $inserted = 0;
        $skipped = 0;

        foreach ($invoices as $inv) {
            $hubspotId = $inv['hubspot_id'];
            $customerId = $hubspotMap[$hubspotId] ?? null;

            if (! $customerId) {
                $this->command->warn("⚠️  Cliente no encontrado para hubspot_id={$hubspotId} (factura {$inv['invoice_number']}) — omitida");
                $skipped++;

                continue;
            }

            // Evitar duplicados por invoice_number
            $exists = DB::table('invoices')
                ->where('invoice_number', $inv['invoice_number'])
                ->exists();

            if ($exists) {
                $this->command->warn("⚠️  Factura {$inv['invoice_number']} ya existe — omitida");
                $skipped++;

                continue;
            }

            $invoiceId = DB::table('invoices')->insertGetId([
                'customer_id' => $customerId,
                'order_id' => null,
                'invoice_number' => $inv['invoice_number'],
                'status' => $inv['status'],
                'total_amount' => $inv['total_amount'],
                'issued_at' => $inv['issued_at'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Líneas de factura
            foreach ($linesByInvoice[$inv['factura_id']] ?? [] as $line) {
                $product = DB::table('products')->where('sku', $line['sku'])->first();

                DB::table('invoice_items')->insert([
                    'invoice_id' => $invoiceId,
                    'product_id' => $product?->id ?? null,
                    'description' => $line['descripcion'],
                    'quantity' => $line['cantidad'],
                    'unit_price' => $line['precio_unitario'],
                    'discount_percent' => $line['discount_percent'] ?? 0,
                    'total_price' => $line['subtotal'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $inserted++;
            $this->command->info("✅ Factura {$inv['invoice_number']} → cliente ID {$customerId}");
        }

        $this->command->info('---');
        $this->command->info("Total insertadas: {$inserted} | Omitidas: {$skipped}");
    }

    private function buildHubspotMap(): array
    {
        return DB::table('customers')
            ->whereNotNull('hubspot_company_id')
            ->pluck('id', 'hubspot_company_id')
            ->toArray();
    }

    private function getInvoices(): array
    {
        // Estado: Pagado → paid | Pendiente → issued | vacío → issued
        // Factura 23 CANCELAR omitida
        // HubSpot IDs resueltos para clientes con #N/A original:
        //   ALONSO      → 429008446682
        //   PAPO        → 429007730890  (KACHAFAZ SL)
        //   CHEMO       → 428840830178
        //   KALIDAD     → 428801234142
        //   INTERCOPY   → 428840853749
        //   LO DE POCHO → 429557478613
        //   PALADAR NEGRO → 431124761819

        return [
            ['factura_id' => 1,  'invoice_number' => 'HORECA2025-00001', 'hubspot_id' => '422777310396', 'status' => 'issued', 'total_amount' => 430.5664,  'issued_at' => '2025-10-11'],
            ['factura_id' => 2,  'invoice_number' => 'HORECA2025-00002', 'hubspot_id' => '423549031616', 'status' => 'paid',   'total_amount' => 225.4956,  'issued_at' => '2025-10-11'],
            ['factura_id' => 3,  'invoice_number' => 'HORECA2025-00003', 'hubspot_id' => '425826400474', 'status' => 'paid',   'total_amount' => 86.3577,   'issued_at' => '2025-10-26'],
            ['factura_id' => 4,  'invoice_number' => 'HORECA2025-00004', 'hubspot_id' => '424730192109', 'status' => 'paid',   'total_amount' => 67.5664,   'issued_at' => '2025-10-26'],
            ['factura_id' => 5,  'invoice_number' => 'HORECA2025-00005', 'hubspot_id' => '426146644198', 'status' => 'issued', 'total_amount' => 37.5826,   'issued_at' => '2025-10-26'],
            ['factura_id' => 6,  'invoice_number' => 'HORECA2025-00006', 'hubspot_id' => '426146642153', 'status' => 'issued', 'total_amount' => 48.7751,   'issued_at' => '2025-10-26'],
            ['factura_id' => 7,  'invoice_number' => 'HORECA2025-00007', 'hubspot_id' => '426146643181', 'status' => 'issued', 'total_amount' => 59.9676,   'issued_at' => '2025-10-26'],
            ['factura_id' => 8,  'invoice_number' => 'HORECA2025-00008', 'hubspot_id' => '429008446682', 'status' => 'paid',   'total_amount' => 67.155,    'issued_at' => '2025-10-28'],
            ['factura_id' => 9,  'invoice_number' => 'HORECA2025-00009', 'hubspot_id' => '423549031616', 'status' => 'paid',   'total_amount' => 158.3406,  'issued_at' => '2025-11-03'],
            ['factura_id' => 10, 'invoice_number' => 'HORECA2025-00010', 'hubspot_id' => '422777310396', 'status' => 'issued', 'total_amount' => 220.2684,  'issued_at' => '2025-11-01'],
            ['factura_id' => 11, 'invoice_number' => 'HORECA2025-00011', 'hubspot_id' => '422777310396', 'status' => 'issued', 'total_amount' => 79.497,    'issued_at' => '2025-11-01'],
            ['factura_id' => 12, 'invoice_number' => 'HORECA2025-00012', 'hubspot_id' => '422777310396', 'status' => 'issued', 'total_amount' => 134.31,    'issued_at' => '2025-11-08'],
            ['factura_id' => 13, 'invoice_number' => 'HORECA2025-00013', 'hubspot_id' => '415150265590', 'status' => 'paid',   'total_amount' => 158.994,   'issued_at' => '2025-11-15'],
            ['factura_id' => 14, 'invoice_number' => 'HORECA2025-00014', 'hubspot_id' => '415150265590', 'status' => 'paid',   'total_amount' => 67.155,    'issued_at' => '2025-11-15'],
            ['factura_id' => 15, 'invoice_number' => 'HORECA2025-00015', 'hubspot_id' => '429007730890', 'status' => 'paid',   'total_amount' => 592.6338,  'issued_at' => '2025-11-25'],
            ['factura_id' => 16, 'invoice_number' => 'HORECA2025-00016', 'hubspot_id' => '429008446682', 'status' => 'paid',   'total_amount' => 201.465,   'issued_at' => '2025-10-26'],
            ['factura_id' => 17, 'invoice_number' => 'HORECA2025-00017', 'hubspot_id' => '425826401481', 'status' => 'issued', 'total_amount' => 56.3739,   'issued_at' => '2025-12-09'],
            ['factura_id' => 18, 'invoice_number' => 'HORECA2025-00018', 'hubspot_id' => '422777310396', 'status' => 'issued', 'total_amount' => 179.08,    'issued_at' => '2025-12-09'],
            ['factura_id' => 19, 'invoice_number' => 'HORECA2025-00019', 'hubspot_id' => '422777310396', 'status' => 'issued', 'total_amount' => 268.62,    'issued_at' => '2025-12-09'],
            ['factura_id' => 20, 'invoice_number' => 'HORECA2025-00020', 'hubspot_id' => '415150265590', 'status' => 'paid',   'total_amount' => 158.994,   'issued_at' => '2025-12-10'],
            ['factura_id' => 21, 'invoice_number' => 'HORECA2025-00021', 'hubspot_id' => '425826401481', 'status' => 'issued', 'total_amount' => 33.5775,   'issued_at' => '2025-12-11'],
            ['factura_id' => 22, 'invoice_number' => 'HORECA2025-00022', 'hubspot_id' => '428840830178', 'status' => 'paid',   'total_amount' => 5530.305,  'issued_at' => '2025-12-18'],
            // 23 CANCELADA — omitida
            ['factura_id' => 24, 'invoice_number' => 'HORECA2025-00024', 'hubspot_id' => '415150265590', 'status' => 'paid',   'total_amount' => 223.2813,  'issued_at' => '2025-12-27'],
            ['factura_id' => 25, 'invoice_number' => 'HORECA2025-00025', 'hubspot_id' => '422777310396', 'status' => 'issued', 'total_amount' => 91.839,    'issued_at' => '2025-12-27'],
            ['factura_id' => 26, 'invoice_number' => 'HORECA2025-00026', 'hubspot_id' => '426146642153', 'status' => 'issued', 'total_amount' => 67.155,    'issued_at' => '2025-12-29'],
            ['factura_id' => 27, 'invoice_number' => 'HORECA2025-00027', 'hubspot_id' => '426146643181', 'status' => 'issued', 'total_amount' => 67.155,    'issued_at' => '2025-12-29'],
            ['factura_id' => 28, 'invoice_number' => 'HORECA2025-00028', 'hubspot_id' => '425826401481', 'status' => 'issued', 'total_amount' => 56.3739,   'issued_at' => '2025-12-29'],
            ['factura_id' => 29, 'invoice_number' => 'HORECA2026-00029', 'hubspot_id' => '428259928300', 'status' => 'paid',   'total_amount' => 362.274,   'issued_at' => '2026-02-20'],
            ['factura_id' => 30, 'invoice_number' => 'HORECA2026-00030', 'hubspot_id' => '425826401481', 'status' => 'issued', 'total_amount' => 33.5775,   'issued_at' => '2026-02-24'],
            ['factura_id' => 31, 'invoice_number' => 'HORECA2026-00031', 'hubspot_id' => '426146644198', 'status' => 'issued', 'total_amount' => 37.5826,   'issued_at' => '2026-02-24'],
            ['factura_id' => 32, 'invoice_number' => 'HORECA2025-00032', 'hubspot_id' => '425825686748', 'status' => 'issued', 'total_amount' => 67.155,    'issued_at' => '2025-02-24'],
            ['factura_id' => 33, 'invoice_number' => 'HORECA2025-00033', 'hubspot_id' => '422777310396', 'status' => 'issued', 'total_amount' => 75.52215,  'issued_at' => '2025-02-24'],
            ['factura_id' => 34, 'invoice_number' => 'HORECA2025-00034', 'hubspot_id' => '424539151597', 'status' => 'paid',   'total_amount' => 182.3712,  'issued_at' => '2025-02-27'],
            ['factura_id' => 35, 'invoice_number' => 'HORECA2026-00035', 'hubspot_id' => '415150265590', 'status' => 'paid',   'total_amount' => 193.73673, 'issued_at' => '2026-03-06'],
            ['factura_id' => 36, 'invoice_number' => 'HORECA2026-00036', 'hubspot_id' => '420189971690', 'status' => 'paid',   'total_amount' => 112.7478,  'issued_at' => '2026-03-10'],
            ['factura_id' => 37, 'invoice_number' => 'HORECA2026-00037', 'hubspot_id' => '415150265590', 'status' => 'issued', 'total_amount' => 389.81844, 'issued_at' => '2026-03-14'],
            ['factura_id' => 38, 'invoice_number' => 'HORECA2026-00038', 'hubspot_id' => '415162838217', 'status' => 'issued', 'total_amount' => 158.994,   'issued_at' => '2026-03-17'],
            ['factura_id' => 39, 'invoice_number' => 'HORECA2025-00039', 'hubspot_id' => '428801234142', 'status' => 'paid',   'total_amount' => 250.833,   'issued_at' => '2025-03-20'],
            ['factura_id' => 40, 'invoice_number' => 'HORECA2026-00040', 'hubspot_id' => '416584819948', 'status' => 'paid',   'total_amount' => 112.7478,  'issued_at' => '2026-03-20'],
            ['factura_id' => 41, 'invoice_number' => 'HORECA2026-00041', 'hubspot_id' => '422773001417', 'status' => 'paid',   'total_amount' => 128.6835,  'issued_at' => '2026-03-20'],
            ['factura_id' => 42, 'invoice_number' => 'HORECA2026-00042', 'hubspot_id' => '422731248843', 'status' => 'paid',   'total_amount' => 75.988,    'issued_at' => '2026-03-20'],
            ['factura_id' => 43, 'invoice_number' => 'HORECA2026-00043', 'hubspot_id' => '422777310396', 'status' => 'issued', 'total_amount' => 257.53398, 'issued_at' => '2026-03-24'],
            ['factura_id' => 44, 'invoice_number' => 'HORECA2026-00044', 'hubspot_id' => '420906114262', 'status' => 'issued', 'total_amount' => 91.1856,   'issued_at' => '2026-03-25'],
            ['factura_id' => 45, 'invoice_number' => 'HORECA2026-00045', 'hubspot_id' => '421668865242', 'status' => 'issued', 'total_amount' => 1063.4448, 'issued_at' => '2026-03-26'],
            ['factura_id' => 46, 'invoice_number' => 'HORECA2026-00046', 'hubspot_id' => '423549031616', 'status' => 'issued', 'total_amount' => 257.53398, 'issued_at' => '2026-03-31'],
            ['factura_id' => 47, 'invoice_number' => 'HORECA2026-00047', 'hubspot_id' => '424539151597', 'status' => 'paid',   'total_amount' => 182.3712,  'issued_at' => '2026-03-31'],
            ['factura_id' => 48, 'invoice_number' => 'HORECA2026-00048', 'hubspot_id' => '415150265590', 'status' => 'issued', 'total_amount' => 87.24705,  'issued_at' => '2026-03-31'],
            ['factura_id' => 49, 'invoice_number' => 'HORECA2026-00049', 'hubspot_id' => '424730192109', 'status' => 'issued', 'total_amount' => 63.9727,   'issued_at' => '2026-04-03'],
            ['factura_id' => 50, 'invoice_number' => 'HORECA2026-00050', 'hubspot_id' => '423549031616', 'status' => 'issued', 'total_amount' => 86.62632,  'issued_at' => '2026-04-10'],
            ['factura_id' => 51, 'invoice_number' => 'HORECA2026-00051', 'hubspot_id' => '426146643181', 'status' => 'issued', 'total_amount' => 52.7802,   'issued_at' => '2026-04-10'],
            ['factura_id' => 52, 'invoice_number' => 'HORECA2026-00052', 'hubspot_id' => '422777310396', 'status' => 'issued', 'total_amount' => 45.5928,   'issued_at' => '2026-04-14'],
            ['factura_id' => 53, 'invoice_number' => 'HORECA2026-00053', 'hubspot_id' => '422575950038', 'status' => 'paid',   'total_amount' => 45.5928,   'issued_at' => '2026-04-14'],
            ['factura_id' => 54, 'invoice_number' => 'HORECA2026-00054', 'hubspot_id' => '416584819948', 'status' => 'issued', 'total_amount' => 158.3406,  'issued_at' => '2026-04-20'],
            ['factura_id' => 55, 'invoice_number' => 'HORECA2026-00055', 'hubspot_id' => '422773001417', 'status' => 'paid',   'total_amount' => 45.5928,   'issued_at' => '2026-04-25'],
            ['factura_id' => 56, 'invoice_number' => 'HORECA2026-00056', 'hubspot_id' => '420906114262', 'status' => 'issued', 'total_amount' => 91.1856,   'issued_at' => '2026-04-28'],
            ['factura_id' => 57, 'invoice_number' => 'HORECA2026-00057', 'hubspot_id' => '428259928300', 'status' => 'issued', 'total_amount' => 249.5262,  'issued_at' => '2026-04-28'],
            ['factura_id' => 58, 'invoice_number' => 'HORECA2026-00058', 'hubspot_id' => '422777310396', 'status' => 'issued', 'total_amount' => 205.1676,  'issued_at' => '2026-04-30'],
            ['factura_id' => 59, 'invoice_number' => 'HORECA2026-00059', 'hubspot_id' => '422777310396', 'status' => 'issued', 'total_amount' => 45.5928,   'issued_at' => '2026-04-30'],
            ['factura_id' => 60, 'invoice_number' => 'HORECA2026-00060', 'hubspot_id' => '428840853749', 'status' => 'issued', 'total_amount' => 45.5928,   'issued_at' => '2026-04-30'],
            ['factura_id' => 61, 'invoice_number' => 'HORECA2026-00061', 'hubspot_id' => '425349174471', 'status' => 'issued', 'total_amount' => 91.839,    'issued_at' => '2026-05-06'],
            ['factura_id' => 62, 'invoice_number' => 'HORECA2026-00062', 'hubspot_id' => '428801234142', 'status' => 'issued', 'total_amount' => 271.22271, 'issued_at' => '2026-05-07'],
            ['factura_id' => 63, 'invoice_number' => 'HORECA2026-00063', 'hubspot_id' => '424539151597', 'status' => 'issued', 'total_amount' => 91.1856,   'issued_at' => '2026-05-11'],
            ['factura_id' => 64, 'invoice_number' => 'HORECA2026-00064', 'hubspot_id' => '425349174471', 'status' => 'issued', 'total_amount' => 91.839,    'issued_at' => '2026-05-11'],
            ['factura_id' => 65, 'invoice_number' => 'HORECA2026-00065', 'hubspot_id' => '429557478613', 'status' => 'issued', 'total_amount' => 203.9334,  'issued_at' => '2026-05-11'],
            ['factura_id' => 66, 'invoice_number' => 'HORECA2026-00066', 'hubspot_id' => '415162838217', 'status' => 'issued', 'total_amount' => 112.7478,  'issued_at' => '2026-05-12'],
            ['factura_id' => 67, 'invoice_number' => 'HORECA2026-00067', 'hubspot_id' => '420189971690', 'status' => 'issued', 'total_amount' => 112.7478,  'issued_at' => '2026-05-15'],
            ['factura_id' => 68, 'invoice_number' => 'HORECA2026-00068', 'hubspot_id' => '415150265590', 'status' => 'issued', 'total_amount' => 194.35746, 'issued_at' => '2026-05-15'],
            ['factura_id' => 69, 'invoice_number' => 'HORECA2026-00069', 'hubspot_id' => '422777310396', 'status' => 'issued', 'total_amount' => 112.7478,  'issued_at' => '2026-05-15'],
            ['factura_id' => 70, 'invoice_number' => 'HORECA2026-00070', 'hubspot_id' => '422777310396', 'status' => 'issued', 'total_amount' => 45.5928,   'issued_at' => '2026-05-15'],
            ['factura_id' => 71, 'invoice_number' => 'HORECA2026-00071', 'hubspot_id' => '431124761819', 'status' => 'issued', 'total_amount' => 3166.812,  'issued_at' => '2026-05-22'],
            ['factura_id' => 72, 'invoice_number' => 'HORECA2026-00072', 'hubspot_id' => '422777310396', 'status' => 'issued', 'total_amount' => 67.155,    'issued_at' => '2026-05-22'],
            ['factura_id' => 73, 'invoice_number' => 'HORECA2026-00073', 'hubspot_id' => '429557478613', 'status' => 'issued', 'total_amount' => 45.5928,   'issued_at' => '2026-05-23'],
            ['factura_id' => 74, 'invoice_number' => 'HORECA2026-00074', 'hubspot_id' => '423549031616', 'status' => 'issued', 'total_amount' => 86.62632,  'issued_at' => '2026-05-26'],
            ['factura_id' => 75, 'invoice_number' => 'HORECA2026-00075', 'hubspot_id' => '416584819948', 'status' => 'issued', 'total_amount' => 158.3406,  'issued_at' => '2026-05-28'],
            ['factura_id' => 76, 'invoice_number' => 'HORECA2026-00076', 'hubspot_id' => '429557478613', 'status' => 'issued', 'total_amount' => 127.9454,  'issued_at' => '2026-06-02'],
        ];
    }

    private function getLines(): array
    {
        return [
            ['factura_id' => 1,  'sku' => 'SER-MAL-750', 'descripcion' => 'Serbal Malbec',                    'cantidad' => 10, 'precio_unitario' => 6.28,  'subtotal' => 62.8],
            ['factura_id' => 1,  'sku' => 'CAT-CHA-750', 'descripcion' => 'Catalpa Chardonnay',               'cantidad' => 6,  'precio_unitario' => 9.25,  'subtotal' => 55.5],
            ['factura_id' => 1,  'sku' => 'CAT-MAL-750', 'descripcion' => 'Catalpa Malbec',                   'cantidad' => 6,  'precio_unitario' => 9.25,  'subtotal' => 55.5],
            ['factura_id' => 1,  'sku' => 'ATA-MAL-750', 'descripcion' => 'Atamisque Malbec',                 'cantidad' => 6,  'precio_unitario' => 12.65, 'subtotal' => 75.9],
            ['factura_id' => 1,  'sku' => 'ATA-ASS-750', 'descripcion' => 'Atamisque Assemblage',             'cantidad' => 6,  'precio_unitario' => 17.69, 'subtotal' => 106.14],
            ['factura_id' => 2,  'sku' => 'SER-MAL-750', 'descripcion' => 'Serbal Malbec',                    'cantidad' => 12, 'precio_unitario' => 6.28,  'subtotal' => 75.36],
            ['factura_id' => 2,  'sku' => 'CAT-CHA-750', 'descripcion' => 'Catalpa Chardonnay',               'cantidad' => 6,  'precio_unitario' => 9.25,  'subtotal' => 55.5],
            ['factura_id' => 2,  'sku' => 'CAT-MAL-750', 'descripcion' => 'Catalpa Malbec',                   'cantidad' => 6,  'precio_unitario' => 9.25,  'subtotal' => 55.5],
            ['factura_id' => 3,  'sku' => 'SER-MAL-750', 'descripcion' => 'Serbal Malbec',                    'cantidad' => 4,  'precio_unitario' => 6.28,  'subtotal' => 25.12],
            ['factura_id' => 3,  'sku' => 'CAT-CHA-750', 'descripcion' => 'Catalpa Chardonnay',               'cantidad' => 1,  'precio_unitario' => 9.25,  'subtotal' => 9.25],
            ['factura_id' => 3,  'sku' => 'CAT-MAL-750', 'descripcion' => 'Catalpa Malbec',                   'cantidad' => 4,  'precio_unitario' => 9.25,  'subtotal' => 37.0],
            ['factura_id' => 4,  'sku' => 'SER-MAL-750', 'descripcion' => 'Serbal Malbec',                    'cantidad' => 3,  'precio_unitario' => 6.28,  'subtotal' => 18.84],
            ['factura_id' => 4,  'sku' => 'CAT-CHA-750', 'descripcion' => 'Catalpa Chardonnay',               'cantidad' => 1,  'precio_unitario' => 9.25,  'subtotal' => 9.25],
            ['factura_id' => 4,  'sku' => 'CAT-MAL-750', 'descripcion' => 'Catalpa Malbec',                   'cantidad' => 3,  'precio_unitario' => 9.25,  'subtotal' => 27.75],
            ['factura_id' => 5,  'sku' => 'SER-MAL-750', 'descripcion' => 'Serbal Malbec',                    'cantidad' => 2,  'precio_unitario' => 6.28,  'subtotal' => 12.56],
            ['factura_id' => 5,  'sku' => 'CAT-CHA-750', 'descripcion' => 'Catalpa Chardonnay',               'cantidad' => 1,  'precio_unitario' => 9.25,  'subtotal' => 9.25],
            ['factura_id' => 5,  'sku' => 'CAT-MAL-750', 'descripcion' => 'Catalpa Malbec',                   'cantidad' => 1,  'precio_unitario' => 9.25,  'subtotal' => 9.25],
            ['factura_id' => 6,  'sku' => 'SER-MAL-750', 'descripcion' => 'Serbal Malbec',                    'cantidad' => 2,  'precio_unitario' => 6.28,  'subtotal' => 12.56],
            ['factura_id' => 6,  'sku' => 'CAT-CHA-750', 'descripcion' => 'Catalpa Chardonnay',               'cantidad' => 1,  'precio_unitario' => 9.25,  'subtotal' => 9.25],
            ['factura_id' => 6,  'sku' => 'CAT-MAL-750', 'descripcion' => 'Catalpa Malbec',                   'cantidad' => 2,  'precio_unitario' => 9.25,  'subtotal' => 18.5],
            ['factura_id' => 7,  'sku' => 'SER-MAL-750', 'descripcion' => 'Serbal Malbec',                    'cantidad' => 2,  'precio_unitario' => 6.28,  'subtotal' => 12.56],
            ['factura_id' => 7,  'sku' => 'CAT-CHA-750', 'descripcion' => 'Catalpa Chardonnay',               'cantidad' => 1,  'precio_unitario' => 9.25,  'subtotal' => 9.25],
            ['factura_id' => 7,  'sku' => 'CAT-MAL-750', 'descripcion' => 'Catalpa Malbec',                   'cantidad' => 3,  'precio_unitario' => 9.25,  'subtotal' => 27.75],
            ['factura_id' => 8,  'sku' => 'CAT-MAL-750', 'descripcion' => 'Catalpa Malbec',                   'cantidad' => 6,  'precio_unitario' => 9.25,  'subtotal' => 55.5],
            ['factura_id' => 9,  'sku' => 'SER-MAL-750', 'descripcion' => 'Serbal Malbec',                    'cantidad' => 12, 'precio_unitario' => 6.28,  'subtotal' => 75.36],
            ['factura_id' => 9,  'sku' => 'CAT-CHA-750', 'descripcion' => 'Catalpa Chardonnay',               'cantidad' => 6,  'precio_unitario' => 9.25,  'subtotal' => 55.5],
            ['factura_id' => 10, 'sku' => 'ATA-MAL-750', 'descripcion' => 'Atamisque Malbec',                 'cantidad' => 6,  'precio_unitario' => 12.65, 'subtotal' => 75.9],
            ['factura_id' => 10, 'sku' => 'ATA-ASS-750', 'descripcion' => 'Atamisque Assemblage',             'cantidad' => 6,  'precio_unitario' => 17.69, 'subtotal' => 106.14],
            ['factura_id' => 11, 'sku' => 'CAT-MAL-750', 'descripcion' => 'Catalpa Malbec',                   'cantidad' => 3,  'precio_unitario' => 9.25,  'subtotal' => 27.75],
            ['factura_id' => 11, 'sku' => 'ATA-MAL-750', 'descripcion' => 'Atamisque Malbec',                 'cantidad' => 3,  'precio_unitario' => 12.65, 'subtotal' => 37.95],
            ['factura_id' => 12, 'sku' => 'CAT-MAL-750', 'descripcion' => 'Catalpa Malbec',                   'cantidad' => 12, 'precio_unitario' => 9.25,  'subtotal' => 111.0],
            ['factura_id' => 13, 'sku' => 'CAT-MAL-750', 'descripcion' => 'Catalpa Malbec',                   'cantidad' => 6,  'precio_unitario' => 9.25,  'subtotal' => 55.5],
            ['factura_id' => 13, 'sku' => 'ATA-MAL-750', 'descripcion' => 'Atamisque Malbec',                 'cantidad' => 6,  'precio_unitario' => 12.65, 'subtotal' => 75.9],
            ['factura_id' => 14, 'sku' => 'CAT-CHA-750', 'descripcion' => 'Catalpa Chardonnay',               'cantidad' => 6,  'precio_unitario' => 9.25,  'subtotal' => 55.5],
            ['factura_id' => 15, 'sku' => 'CAT-CHA-750', 'descripcion' => 'Catalpa Chardonnay',               'cantidad' => 18, 'precio_unitario' => 9.25,  'subtotal' => 166.5],
            ['factura_id' => 15, 'sku' => 'CAT-MAL-750', 'descripcion' => 'Catalpa Malbec',                   'cantidad' => 12, 'precio_unitario' => 9.25,  'subtotal' => 111.0],
            ['factura_id' => 15, 'sku' => 'ATA-ASS-750', 'descripcion' => 'Atamisque Assemblage',             'cantidad' => 12, 'precio_unitario' => 17.69, 'subtotal' => 212.28],
            ['factura_id' => 16, 'sku' => 'CAT-MAL-750', 'descripcion' => 'Catalpa Malbec',                   'cantidad' => 18, 'precio_unitario' => 9.25,  'subtotal' => 166.5],
            ['factura_id' => 17, 'sku' => 'SER-MAL-750', 'descripcion' => 'Serbal Malbec',                    'cantidad' => 3,  'precio_unitario' => 6.28,  'subtotal' => 18.84],
            ['factura_id' => 17, 'sku' => 'CAT-MAL-750', 'descripcion' => 'Catalpa Malbec',                   'cantidad' => 3,  'precio_unitario' => 9.25,  'subtotal' => 27.75],
            ['factura_id' => 18, 'sku' => 'CAT-MAL-750', 'descripcion' => 'Catalpa Malbec',                   'cantidad' => 16, 'precio_unitario' => 9.25,  'subtotal' => 148.0],
            ['factura_id' => 19, 'sku' => 'CAT-MAL-750', 'descripcion' => 'Catalpa Malbec',                   'cantidad' => 24, 'precio_unitario' => 9.25,  'subtotal' => 222.0],
            ['factura_id' => 20, 'sku' => 'CAT-CHA-750', 'descripcion' => 'Catalpa Chardonnay',               'cantidad' => 6,  'precio_unitario' => 9.25,  'subtotal' => 55.5],
            ['factura_id' => 20, 'sku' => 'ATA-MAL-750', 'descripcion' => 'Atamisque Malbec',                 'cantidad' => 6,  'precio_unitario' => 12.65, 'subtotal' => 75.9],
            ['factura_id' => 21, 'sku' => 'CAT-MAL-750', 'descripcion' => 'Catalpa Malbec',                   'cantidad' => 3,  'precio_unitario' => 9.25,  'subtotal' => 27.75],
            ['factura_id' => 22, 'sku' => 'CAT-MAL-750', 'descripcion' => 'Catalpa Malbec',                   'cantidad' => 330, 'precio_unitario' => 9.25,  'subtotal' => 3052.5],
            ['factura_id' => 22, 'sku' => 'ATA-MAL-750', 'descripcion' => 'Atamisque Malbec',                 'cantidad' => 37, 'precio_unitario' => 12.65, 'subtotal' => 468.05],
            ['factura_id' => 22, 'sku' => 'ATA-MAL-750', 'descripcion' => 'Atamisque Malbec',                 'cantidad' => 83, 'precio_unitario' => 12.65, 'subtotal' => 1049.95],
            ['factura_id' => 24, 'sku' => 'CAT-CHA-750', 'descripcion' => 'Catalpa Chardonnay',               'cantidad' => 6,  'precio_unitario' => 9.25,  'subtotal' => 55.5],
            ['factura_id' => 24, 'sku' => 'ATA-MAL-750', 'descripcion' => 'Atamisque Malbec',                 'cantidad' => 12, 'precio_unitario' => 12.65, 'subtotal' => 129.03,  'discount_percent' => 15],
            ['factura_id' => 25, 'sku' => 'ATA-MAL-750', 'descripcion' => 'Atamisque Malbec',                 'cantidad' => 6,  'precio_unitario' => 12.65, 'subtotal' => 75.9],
            ['factura_id' => 26, 'sku' => 'CAT-MAL-750', 'descripcion' => 'Catalpa Malbec',                   'cantidad' => 6,  'precio_unitario' => 9.25,  'subtotal' => 55.5],
            ['factura_id' => 27, 'sku' => 'CAT-CHA-750', 'descripcion' => 'Catalpa Chardonnay',               'cantidad' => 3,  'precio_unitario' => 9.25,  'subtotal' => 27.75],
            ['factura_id' => 27, 'sku' => 'CAT-MAL-750', 'descripcion' => 'Catalpa Malbec',                   'cantidad' => 3,  'precio_unitario' => 9.25,  'subtotal' => 27.75],
            ['factura_id' => 28, 'sku' => 'SER-MAL-750', 'descripcion' => 'Serbal Malbec',                    'cantidad' => 3,  'precio_unitario' => 6.28,  'subtotal' => 18.84],
            ['factura_id' => 28, 'sku' => 'CAT-MAL-750', 'descripcion' => 'Catalpa Malbec',                   'cantidad' => 3,  'precio_unitario' => 9.25,  'subtotal' => 27.75],
            ['factura_id' => 29, 'sku' => 'SER-MAL-750', 'descripcion' => 'Serbal Malbec',                    'cantidad' => 18, 'precio_unitario' => 6.28,  'subtotal' => 113.04],
            ['factura_id' => 29, 'sku' => 'SER-CAB-750', 'descripcion' => 'Serbal Cabernet Franc',            'cantidad' => 12, 'precio_unitario' => 6.28,  'subtotal' => 75.36],
            ['factura_id' => 29, 'sku' => 'CAT-MAL-750', 'descripcion' => 'Catalpa Malbec',                   'cantidad' => 12, 'precio_unitario' => 9.25,  'subtotal' => 111.0],
            ['factura_id' => 30, 'sku' => 'CAT-CHA-750', 'descripcion' => 'Catalpa Chardonnay',               'cantidad' => 1,  'precio_unitario' => 9.25,  'subtotal' => 9.25],
            ['factura_id' => 30, 'sku' => 'CAT-MAL-750', 'descripcion' => 'Catalpa Malbec',                   'cantidad' => 2,  'precio_unitario' => 9.25,  'subtotal' => 18.5],
            ['factura_id' => 31, 'sku' => 'SER-MAL-750', 'descripcion' => 'Serbal Malbec',                    'cantidad' => 2,  'precio_unitario' => 6.28,  'subtotal' => 12.56],
            ['factura_id' => 31, 'sku' => 'CAT-CHA-750', 'descripcion' => 'Catalpa Chardonnay',               'cantidad' => 1,  'precio_unitario' => 9.25,  'subtotal' => 9.25],
            ['factura_id' => 31, 'sku' => 'CAT-MAL-750', 'descripcion' => 'Catalpa Malbec',                   'cantidad' => 1,  'precio_unitario' => 9.25,  'subtotal' => 9.25],
            ['factura_id' => 32, 'sku' => 'CAT-CHA-750', 'descripcion' => 'Catalpa Chardonnay',               'cantidad' => 2,  'precio_unitario' => 9.25,  'subtotal' => 18.5],
            ['factura_id' => 32, 'sku' => 'CAT-MAL-750', 'descripcion' => 'Catalpa Malbec',                   'cantidad' => 4,  'precio_unitario' => 9.25,  'subtotal' => 37.0],
            ['factura_id' => 33, 'sku' => 'ATA-MAL-750', 'descripcion' => 'Atamisque Malbec',                 'cantidad' => 3,  'precio_unitario' => 12.65, 'subtotal' => 36.0525, 'discount_percent' => 5],
            ['factura_id' => 33, 'sku' => 'CAT-MAL-750', 'descripcion' => 'Catalpa Malbec',                   'cantidad' => 3,  'precio_unitario' => 9.25,  'subtotal' => 26.3625, 'discount_percent' => 5],
            ['factura_id' => 34, 'sku' => 'SER-PIN-750', 'descripcion' => 'Serbal Pinot Noir',                'cantidad' => 6,  'precio_unitario' => 6.28,  'subtotal' => 37.68],
            ['factura_id' => 34, 'sku' => 'SER-CAB-750', 'descripcion' => 'Serbal Cabernet Franc',            'cantidad' => 6,  'precio_unitario' => 6.28,  'subtotal' => 37.68],
            ['factura_id' => 34, 'sku' => 'SER-MAL-750', 'descripcion' => 'Serbal Malbec',                    'cantidad' => 6,  'precio_unitario' => 6.28,  'subtotal' => 37.68],
            ['factura_id' => 34, 'sku' => 'SER-CHA-750', 'descripcion' => 'Serbal Chardonnay',                'cantidad' => 6,  'precio_unitario' => 6.28,  'subtotal' => 37.68],
            ['factura_id' => 35, 'sku' => 'CAT-MAL-750', 'descripcion' => 'Catalpa Malbec',                   'cantidad' => 6,  'precio_unitario' => 9.25,  'subtotal' => 52.725,  'discount_percent' => 5],
            ['factura_id' => 35, 'sku' => 'SER-CAB-750', 'descripcion' => 'Serbal Cabernet Franc',            'cantidad' => 6,  'precio_unitario' => 6.28,  'subtotal' => 35.796,  'discount_percent' => 5],
            ['factura_id' => 35, 'sku' => 'SER-MAL-750', 'descripcion' => 'Serbal Malbec',                    'cantidad' => 6,  'precio_unitario' => 6.28,  'subtotal' => 35.796,  'discount_percent' => 5],
            ['factura_id' => 35, 'sku' => 'SER-CHA-750', 'descripcion' => 'Serbal Chardonnay',                'cantidad' => 6,  'precio_unitario' => 6.28,  'subtotal' => 35.796,  'discount_percent' => 5],
            ['factura_id' => 36, 'sku' => 'CAT-MAL-750', 'descripcion' => 'Catalpa Malbec',                   'cantidad' => 6,  'precio_unitario' => 9.25,  'subtotal' => 55.5],
            ['factura_id' => 36, 'sku' => 'SER-MAL-750', 'descripcion' => 'Serbal Malbec',                    'cantidad' => 6,  'precio_unitario' => 6.28,  'subtotal' => 37.68],
            ['factura_id' => 37, 'sku' => 'SER-CAB-750', 'descripcion' => 'Serbal Cabernet Franc',            'cantidad' => 18, 'precio_unitario' => 6.28,  'subtotal' => 107.388, 'discount_percent' => 5],
            ['factura_id' => 37, 'sku' => 'SER-MAL-750', 'descripcion' => 'Serbal Malbec',                    'cantidad' => 18, 'precio_unitario' => 6.28,  'subtotal' => 107.388, 'discount_percent' => 5],
            ['factura_id' => 37, 'sku' => 'SER-CHA-750', 'descripcion' => 'Serbal Chardonnay',                'cantidad' => 18, 'precio_unitario' => 6.28,  'subtotal' => 107.388, 'discount_percent' => 5],
            ['factura_id' => 38, 'sku' => 'ATA-MAL-750', 'descripcion' => 'Atamisque Malbec',                 'cantidad' => 6,  'precio_unitario' => 12.65, 'subtotal' => 75.9],
            ['factura_id' => 38, 'sku' => 'CAT-MAL-750', 'descripcion' => 'Catalpa Malbec',                   'cantidad' => 6,  'precio_unitario' => 9.25,  'subtotal' => 55.5],
            ['factura_id' => 39, 'sku' => 'ATA-MAL-750', 'descripcion' => 'Atamisque Malbec',                 'cantidad' => 6,  'precio_unitario' => 12.65, 'subtotal' => 75.9],
            ['factura_id' => 39, 'sku' => 'ATA-CAB-750', 'descripcion' => 'Atamisque Cabernet Sauvignon',     'cantidad' => 6,  'precio_unitario' => 12.65, 'subtotal' => 75.9],
            ['factura_id' => 39, 'sku' => 'CAT-MAL-750', 'descripcion' => 'Catalpa Malbec',                   'cantidad' => 6,  'precio_unitario' => 9.25,  'subtotal' => 55.5],
            ['factura_id' => 40, 'sku' => 'CAT-MAL-750', 'descripcion' => 'Catalpa Malbec',                   'cantidad' => 6,  'precio_unitario' => 9.25,  'subtotal' => 55.5],
            ['factura_id' => 40, 'sku' => 'SER-CAB-750', 'descripcion' => 'Serbal Cabernet Franc',            'cantidad' => 6,  'precio_unitario' => 6.28,  'subtotal' => 37.68],
            ['factura_id' => 41, 'sku' => 'ATA-MAL-750', 'descripcion' => 'Atamisque Malbec',                 'cantidad' => 2,  'precio_unitario' => 12.65, 'subtotal' => 25.3],
            ['factura_id' => 41, 'sku' => 'ATA-CAB-750', 'descripcion' => 'Atamisque Cabernet Sauvignon',     'cantidad' => 1,  'precio_unitario' => 12.65, 'subtotal' => 12.65],
            ['factura_id' => 41, 'sku' => 'CAT-MAL-750', 'descripcion' => 'Catalpa Malbec',                   'cantidad' => 4,  'precio_unitario' => 9.25,  'subtotal' => 37.0],
            ['factura_id' => 41, 'sku' => 'SER-MAL-750', 'descripcion' => 'Serbal Malbec',                    'cantidad' => 1,  'precio_unitario' => 6.28,  'subtotal' => 6.28],
            ['factura_id' => 41, 'sku' => 'SER-CAB-750', 'descripcion' => 'Serbal Cabernet Franc',            'cantidad' => 4,  'precio_unitario' => 6.28,  'subtotal' => 25.12],
            ['factura_id' => 42, 'sku' => 'SER-MAL-750', 'descripcion' => 'Serbal Malbec',                    'cantidad' => 1,  'precio_unitario' => 6.28,  'subtotal' => 6.28],
            ['factura_id' => 42, 'sku' => 'SER-CAB-750', 'descripcion' => 'Serbal Cabernet Franc',            'cantidad' => 4,  'precio_unitario' => 6.28,  'subtotal' => 25.12],
            ['factura_id' => 42, 'sku' => 'SER-CHA-750', 'descripcion' => 'Serbal Chardonnay',                'cantidad' => 5,  'precio_unitario' => 6.28,  'subtotal' => 31.4],
            ['factura_id' => 43, 'sku' => 'CAT-MAL-750', 'descripcion' => 'Catalpa Malbec',                   'cantidad' => 6,  'precio_unitario' => 9.25,  'subtotal' => 52.725,  'discount_percent' => 5],
            ['factura_id' => 43, 'sku' => 'CAT-CHA-750', 'descripcion' => 'Catalpa Chardonnay',               'cantidad' => 6,  'precio_unitario' => 9.25,  'subtotal' => 52.725,  'discount_percent' => 5],
            ['factura_id' => 43, 'sku' => 'SER-MAL-750', 'descripcion' => 'Serbal Malbec',                    'cantidad' => 12, 'precio_unitario' => 6.28,  'subtotal' => 71.592,  'discount_percent' => 5],
            ['factura_id' => 43, 'sku' => 'SER-CHA-750', 'descripcion' => 'Serbal Chardonnay',                'cantidad' => 6,  'precio_unitario' => 6.28,  'subtotal' => 35.796,  'discount_percent' => 5],
            ['factura_id' => 44, 'sku' => 'SER-MAL-750', 'descripcion' => 'Serbal Malbec',                    'cantidad' => 6,  'precio_unitario' => 6.28,  'subtotal' => 37.68],
            ['factura_id' => 44, 'sku' => 'SER-CHA-750', 'descripcion' => 'Serbal Chardonnay',                'cantidad' => 6,  'precio_unitario' => 6.28,  'subtotal' => 37.68],
            ['factura_id' => 45, 'sku' => 'SER-MAL-750', 'descripcion' => 'Serbal Malbec',                    'cantidad' => 30, 'precio_unitario' => 6.28,  'subtotal' => 188.4],
            ['factura_id' => 45, 'sku' => 'SER-CAB-750', 'descripcion' => 'Serbal Cabernet Franc',            'cantidad' => 36, 'precio_unitario' => 6.28,  'subtotal' => 226.08],
            ['factura_id' => 45, 'sku' => 'CAT-MAL-750', 'descripcion' => 'Catalpa Malbec',                   'cantidad' => 24, 'precio_unitario' => 9.25,  'subtotal' => 222.0],
            ['factura_id' => 45, 'sku' => 'CAT-CHA-750', 'descripcion' => 'Catalpa Chardonnay',               'cantidad' => 18, 'precio_unitario' => 9.25,  'subtotal' => 166.5],
            ['factura_id' => 45, 'sku' => 'ATA-MAL-750', 'descripcion' => 'Atamisque Malbec',                 'cantidad' => 6,  'precio_unitario' => 12.65, 'subtotal' => 75.9],
            ['factura_id' => 46, 'sku' => 'SER-MAL-750', 'descripcion' => 'Serbal Malbec',                    'cantidad' => 6,  'precio_unitario' => 6.28,  'subtotal' => 35.796,  'discount_percent' => 5],
            ['factura_id' => 46, 'sku' => 'SER-CAB-750', 'descripcion' => 'Serbal Cabernet Franc',            'cantidad' => 6,  'precio_unitario' => 6.28,  'subtotal' => 35.796,  'discount_percent' => 5],
            ['factura_id' => 46, 'sku' => 'SER-PIN-750', 'descripcion' => 'Serbal Pinot Noir',                'cantidad' => 6,  'precio_unitario' => 6.28,  'subtotal' => 35.796,  'discount_percent' => 5],
            ['factura_id' => 46, 'sku' => 'CAT-MAL-750', 'descripcion' => 'Catalpa Malbec',                   'cantidad' => 6,  'precio_unitario' => 9.25,  'subtotal' => 52.725,  'discount_percent' => 5],
            ['factura_id' => 46, 'sku' => 'CAT-CHA-750', 'descripcion' => 'Catalpa Chardonnay',               'cantidad' => 6,  'precio_unitario' => 9.25,  'subtotal' => 52.725,  'discount_percent' => 5],
            ['factura_id' => 47, 'sku' => 'SER-MAL-750', 'descripcion' => 'Serbal Malbec',                    'cantidad' => 6,  'precio_unitario' => 6.28,  'subtotal' => 37.68],
            ['factura_id' => 47, 'sku' => 'SER-CAB-750', 'descripcion' => 'Serbal Cabernet Franc',            'cantidad' => 6,  'precio_unitario' => 6.28,  'subtotal' => 37.68],
            ['factura_id' => 47, 'sku' => 'SER-CHA-750', 'descripcion' => 'Serbal Chardonnay',                'cantidad' => 12, 'precio_unitario' => 6.28,  'subtotal' => 75.36],
            ['factura_id' => 48, 'sku' => 'ATA-MAL-750', 'descripcion' => 'Atamisque Malbec',                 'cantidad' => 6,  'precio_unitario' => 12.65, 'subtotal' => 72.105,  'discount_percent' => 5],
            ['factura_id' => 49, 'sku' => 'CAT-MAL-750', 'descripcion' => 'Catalpa Malbec',                   'cantidad' => 2,  'precio_unitario' => 9.25,  'subtotal' => 18.5],
            ['factura_id' => 49, 'sku' => 'CAT-CHA-750', 'descripcion' => 'Catalpa Chardonnay',               'cantidad' => 1,  'precio_unitario' => 9.25,  'subtotal' => 9.25],
            ['factura_id' => 49, 'sku' => 'SER-CAB-750', 'descripcion' => 'Serbal Cabernet Franc',            'cantidad' => 2,  'precio_unitario' => 6.28,  'subtotal' => 12.56],
            ['factura_id' => 49, 'sku' => 'SER-MAL-750', 'descripcion' => 'Serbal Malbec',                    'cantidad' => 2,  'precio_unitario' => 6.28,  'subtotal' => 12.56],
            ['factura_id' => 50, 'sku' => 'SER-MAL-750', 'descripcion' => 'Serbal Malbec',                    'cantidad' => 12, 'precio_unitario' => 6.28,  'subtotal' => 71.592,  'discount_percent' => 5],
            ['factura_id' => 51, 'sku' => 'SER-MAL-750', 'descripcion' => 'Serbal Malbec',                    'cantidad' => 2,  'precio_unitario' => 6.28,  'subtotal' => 12.56],
            ['factura_id' => 51, 'sku' => 'SER-CAB-750', 'descripcion' => 'Serbal Cabernet Franc',            'cantidad' => 2,  'precio_unitario' => 6.28,  'subtotal' => 12.56],
            ['factura_id' => 51, 'sku' => 'CAT-MAL-750', 'descripcion' => 'Catalpa Malbec',                   'cantidad' => 2,  'precio_unitario' => 9.25,  'subtotal' => 18.5],
            ['factura_id' => 52, 'sku' => 'SER-CAB-750', 'descripcion' => 'Serbal Cabernet Franc',            'cantidad' => 6,  'precio_unitario' => 6.28,  'subtotal' => 37.68],
            ['factura_id' => 53, 'sku' => 'SER-CAB-750', 'descripcion' => 'Serbal Cabernet Franc',            'cantidad' => 6,  'precio_unitario' => 6.28,  'subtotal' => 37.68],
            ['factura_id' => 54, 'sku' => 'SER-CAB-750', 'descripcion' => 'Serbal Cabernet Franc',            'cantidad' => 6,  'precio_unitario' => 6.28,  'subtotal' => 37.68],
            ['factura_id' => 54, 'sku' => 'SER-MAL-750', 'descripcion' => 'Serbal Malbec',                    'cantidad' => 6,  'precio_unitario' => 6.28,  'subtotal' => 37.68],
            ['factura_id' => 54, 'sku' => 'CAT-MAL-750', 'descripcion' => 'Catalpa Malbec',                   'cantidad' => 6,  'precio_unitario' => 9.25,  'subtotal' => 55.5],
            ['factura_id' => 55, 'sku' => 'SER-CAB-750', 'descripcion' => 'Serbal Cabernet Franc',            'cantidad' => 6,  'precio_unitario' => 6.28,  'subtotal' => 37.68],
            ['factura_id' => 56, 'sku' => 'SER-MAL-750', 'descripcion' => 'Serbal Malbec',                    'cantidad' => 6,  'precio_unitario' => 6.28,  'subtotal' => 37.68],
            ['factura_id' => 56, 'sku' => 'SER-CAB-750', 'descripcion' => 'Serbal Cabernet Franc',            'cantidad' => 6,  'precio_unitario' => 6.28,  'subtotal' => 37.68],
            ['factura_id' => 57, 'sku' => 'SER-MAL-750', 'descripcion' => 'Serbal Malbec',                    'cantidad' => 24, 'precio_unitario' => 6.28,  'subtotal' => 150.72],
            ['factura_id' => 57, 'sku' => 'CAT-MAL-750', 'descripcion' => 'Catalpa Malbec',                   'cantidad' => 6,  'precio_unitario' => 9.25,  'subtotal' => 55.5],
            ['factura_id' => 58, 'sku' => 'SER-PIN-750', 'descripcion' => 'Serbal Pinot Noir',                'cantidad' => 4,  'precio_unitario' => 6.28,  'subtotal' => 25.12],
            ['factura_id' => 58, 'sku' => 'SER-MAL-750', 'descripcion' => 'Serbal Malbec',                    'cantidad' => 11, 'precio_unitario' => 6.28,  'subtotal' => 69.08],
            ['factura_id' => 58, 'sku' => 'SER-CHA-750', 'descripcion' => 'Serbal Chardonnay',                'cantidad' => 6,  'precio_unitario' => 6.28,  'subtotal' => 37.68],
            ['factura_id' => 58, 'sku' => 'SER-CAB-750', 'descripcion' => 'Serbal Cabernet Franc',            'cantidad' => 6,  'precio_unitario' => 6.28,  'subtotal' => 37.68],
            ['factura_id' => 59, 'sku' => 'SER-CAB-750', 'descripcion' => 'Serbal Cabernet Franc',            'cantidad' => 6,  'precio_unitario' => 6.28,  'subtotal' => 37.68],
            ['factura_id' => 60, 'sku' => 'SER-MAL-750', 'descripcion' => 'Serbal Malbec',                    'cantidad' => 6,  'precio_unitario' => 6.28,  'subtotal' => 37.68],
            ['factura_id' => 61, 'sku' => 'ATA-MAL-750', 'descripcion' => 'Atamisque Malbec',                 'cantidad' => 6,  'precio_unitario' => 12.65, 'subtotal' => 75.9],
            ['factura_id' => 62, 'sku' => 'CAT-MER-750', 'descripcion' => 'Catalpa Merlot',                   'cantidad' => 6,  'precio_unitario' => 9.25,  'subtotal' => 52.725,  'discount_percent' => 5],
            ['factura_id' => 62, 'sku' => 'ATA-CHA-750', 'descripcion' => 'Atamisque Chardonnay',             'cantidad' => 6,  'precio_unitario' => 17.69, 'subtotal' => 95.526,  'discount_percent' => 10],
            ['factura_id' => 62, 'sku' => 'ATA-CAB-750', 'descripcion' => 'Atamisque Cabernet Sauvignon',     'cantidad' => 6,  'precio_unitario' => 12.65, 'subtotal' => 75.9],
            ['factura_id' => 63, 'sku' => 'SER-PIN-750', 'descripcion' => 'Serbal Pinot Noir',                'cantidad' => 12, 'precio_unitario' => 6.28,  'subtotal' => 75.36],
            ['factura_id' => 64, 'sku' => 'ATA-MAL-750', 'descripcion' => 'Atamisque Malbec',                 'cantidad' => 6,  'precio_unitario' => 12.65, 'subtotal' => 75.9],
            ['factura_id' => 65, 'sku' => 'SER-CHA-750', 'descripcion' => 'Serbal Chardonnay',                'cantidad' => 12, 'precio_unitario' => 6.28,  'subtotal' => 75.36],
            ['factura_id' => 65, 'sku' => 'SER-PIN-750', 'descripcion' => 'Serbal Pinot Noir',                'cantidad' => 6,  'precio_unitario' => 6.28,  'subtotal' => 37.68],
            ['factura_id' => 65, 'sku' => 'CAT-MAL-750', 'descripcion' => 'Catalpa Malbec',                   'cantidad' => 6,  'precio_unitario' => 9.25,  'subtotal' => 55.5],
            ['factura_id' => 66, 'sku' => 'SER-MAL-750', 'descripcion' => 'Serbal Malbec',                    'cantidad' => 6,  'precio_unitario' => 6.28,  'subtotal' => 37.68],
            ['factura_id' => 66, 'sku' => 'CAT-MAL-750', 'descripcion' => 'Catalpa Malbec',                   'cantidad' => 6,  'precio_unitario' => 9.25,  'subtotal' => 55.5],
            ['factura_id' => 67, 'sku' => 'SER-MAL-750', 'descripcion' => 'Serbal Malbec',                    'cantidad' => 6,  'precio_unitario' => 6.28,  'subtotal' => 37.68],
            ['factura_id' => 67, 'sku' => 'CAT-MAL-750', 'descripcion' => 'Catalpa Malbec',                   'cantidad' => 6,  'precio_unitario' => 9.25,  'subtotal' => 55.5],
            ['factura_id' => 68, 'sku' => 'SER-CAB-750', 'descripcion' => 'Serbal Cabernet Franc',            'cantidad' => 6,  'precio_unitario' => 6.28,  'subtotal' => 35.796,  'discount_percent' => 5],
            ['factura_id' => 68, 'sku' => 'CAT-MAL-750', 'descripcion' => 'Catalpa Malbec',                   'cantidad' => 6,  'precio_unitario' => 9.25,  'subtotal' => 52.725,  'discount_percent' => 5],
            ['factura_id' => 68, 'sku' => 'ATA-MAL-750', 'descripcion' => 'Atamisque Malbec',                 'cantidad' => 6,  'precio_unitario' => 12.65, 'subtotal' => 72.105,  'discount_percent' => 5],
            ['factura_id' => 69, 'sku' => 'SER-PIN-750', 'descripcion' => 'Serbal Pinot Noir',                'cantidad' => 6,  'precio_unitario' => 6.28,  'subtotal' => 37.68],
            ['factura_id' => 69, 'sku' => 'CAT-MAL-750', 'descripcion' => 'Catalpa Malbec',                   'cantidad' => 6,  'precio_unitario' => 9.25,  'subtotal' => 55.5],
            ['factura_id' => 70, 'sku' => 'SER-MAL-750', 'descripcion' => 'Serbal Malbec',                    'cantidad' => 2,  'precio_unitario' => 6.28,  'subtotal' => 12.56],
            ['factura_id' => 70, 'sku' => 'SER-CAB-750', 'descripcion' => 'Serbal Cabernet Franc',            'cantidad' => 2,  'precio_unitario' => 6.28,  'subtotal' => 12.56],
            ['factura_id' => 70, 'sku' => 'SER-PIN-750', 'descripcion' => 'Serbal Pinot Noir',                'cantidad' => 1,  'precio_unitario' => 6.28,  'subtotal' => 6.28],
            ['factura_id' => 70, 'sku' => 'SER-CHA-750', 'descripcion' => 'Serbal Chardonnay',                'cantidad' => 1,  'precio_unitario' => 6.28,  'subtotal' => 6.28],
            ['factura_id' => 71, 'sku' => 'SER-MAL-750', 'descripcion' => 'Serbal Malbec',                    'cantidad' => 240, 'precio_unitario' => 6.28,  'subtotal' => 1507.2],
            ['factura_id' => 71, 'sku' => 'CAT-MAL-750', 'descripcion' => 'Catalpa Malbec',                   'cantidad' => 120, 'precio_unitario' => 9.25,  'subtotal' => 1110.0],
            ['factura_id' => 72, 'sku' => 'CAT-MAL-750', 'descripcion' => 'Catalpa Malbec',                   'cantidad' => 6,  'precio_unitario' => 9.25,  'subtotal' => 55.5],
            ['factura_id' => 73, 'sku' => 'SER-CHA-750', 'descripcion' => 'Serbal Chardonnay',                'cantidad' => 6,  'precio_unitario' => 6.28,  'subtotal' => 37.68],
            ['factura_id' => 74, 'sku' => 'SER-MAL-750', 'descripcion' => 'Serbal Malbec',                    'cantidad' => 12, 'precio_unitario' => 6.28,  'subtotal' => 71.592,  'discount_percent' => 5],
            ['factura_id' => 75, 'sku' => 'SER-MAL-750', 'descripcion' => 'Serbal Malbec',                    'cantidad' => 6,  'precio_unitario' => 6.28,  'subtotal' => 37.68],
            ['factura_id' => 75, 'sku' => 'SER-CAB-750', 'descripcion' => 'Serbal Cabernet Franc',            'cantidad' => 6,  'precio_unitario' => 6.28,  'subtotal' => 37.68],
            ['factura_id' => 75, 'sku' => 'CAT-MAL-750', 'descripcion' => 'Catalpa Malbec',                   'cantidad' => 6,  'precio_unitario' => 9.25,  'subtotal' => 55.5],
            ['factura_id' => 76, 'sku' => 'SER-PIN-750', 'descripcion' => 'Serbal Pinot Noir',                'cantidad' => 6,  'precio_unitario' => 6.28,  'subtotal' => 37.68],
            ['factura_id' => 76, 'sku' => 'SER-CHA-750', 'descripcion' => 'Serbal Chardonnay',                'cantidad' => 2,  'precio_unitario' => 6.28,  'subtotal' => 12.56],
            ['factura_id' => 76, 'sku' => 'CAT-CHA-750', 'descripcion' => 'Catalpa Chardonnay',               'cantidad' => 6,  'precio_unitario' => 9.25,  'subtotal' => 55.5],
        ];
    }
}

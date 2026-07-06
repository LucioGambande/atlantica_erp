<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Support\LegacyCsvReader;
use App\Support\LegacyDateParser;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class LegacyDataImporter
{
    protected string $importPath;

    protected bool $dryRun = false;

    /** @var array<string, Customer> */
    protected array $customersByLegacyId = [];

    /** @var array<string, Customer> */
    protected array $customersByCommercialName = [];

    /** @var array<string, Invoice> */
    protected array $invoicesByLegacyId = [];

    /** @var array<string, int> */
    protected array $productIdsBySku = [];

    /** @var array<string, PaymentMethod> */
    protected array $paymentMethodsBySlug = [];

    /** @var array{created: int, updated: int, skipped: int} */
    protected array $customerStats = ['created' => 0, 'updated' => 0, 'skipped' => 0];

    /** @var array{created: int, updated: int, skipped: int} */
    protected array $invoiceStats = ['created' => 0, 'updated' => 0, 'skipped' => 0];

    /** @var array{created: int, updated: int, skipped: int} */
    protected array $lineStats = ['created' => 0, 'updated' => 0, 'skipped' => 0];

    /** @var array{created: int, updated: int, skipped: int} */
    protected array $paymentStats = ['created' => 0, 'updated' => 0, 'skipped' => 0];

    /** @var list<string> */
    protected array $skippedInvoices = [];

    /** @var list<string> */
    protected array $skippedLines = [];

    /** @var list<string> */
    protected array $skippedPayments = [];

    /** @var list<array{row: int, data: array<string, string>}> */
    protected array $customerRows = [];

    /** @var list<array{row: int, data: array<string, string>}> */
    protected array $invoiceRows = [];

    /** @var list<array{row: int, data: array<string, string>}> */
    protected array $lineRows = [];

    /** @var list<array{row: int, data: array<string, string>}> */
    protected array $paymentRows = [];

    public function __construct(
        protected PaymentDetailService $paymentDetailService,
        protected AccountStatementService $accountStatementService,
    ) {
    }

    public function import(bool $dryRun = false): void
    {
        $this->dryRun = $dryRun;
        $this->importPath = base_path();
        $this->resetState();

        $this->loadCsvFiles();
        $this->preloadLookups();

        $callback = function (): void {
            $this->importCustomers();
            $this->importInvoices();
            $this->importInvoiceLines();
            $this->importPayments();

            if (! $this->dryRun) {
                $this->rebuildLedgers();
            }
        };

        try {
            if ($this->dryRun) {
                $callback();
            } else {
                Invoice::skipSequenceValidation(true);

                try {
                    DB::transaction(function () use ($callback): void {
                        Customer::withoutEvents(function () use ($callback): void {
                            Invoice::withoutEvents(function () use ($callback): void {
                                InvoiceItem::withoutEvents(function () use ($callback): void {
                                    Payment::withoutEvents($callback);
                                });
                            });
                        });
                    });
                } finally {
                    Invoice::skipSequenceValidation(false);
                }
            }
        } catch (Throwable $exception) {
            throw new RuntimeException($exception->getMessage(), previous: $exception);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        return [
            'customers' => $this->customerStats,
            'invoices' => $this->invoiceStats,
            'lines' => $this->lineStats,
            'payments' => $this->paymentStats,
            'skipped_invoices' => $this->skippedInvoices,
            'skipped_lines' => $this->skippedLines,
            'skipped_payments' => $this->skippedPayments,
            'comparison' => $this->buildComparisonTotals(),
        ];
    }

    protected function resetState(): void
    {
        $this->customersByLegacyId = [];
        $this->customersByCommercialName = [];
        $this->invoicesByLegacyId = [];
        $this->customerStats = ['created' => 0, 'updated' => 0, 'skipped' => 0];
        $this->invoiceStats = ['created' => 0, 'updated' => 0, 'skipped' => 0];
        $this->lineStats = ['created' => 0, 'updated' => 0, 'skipped' => 0];
        $this->paymentStats = ['created' => 0, 'updated' => 0, 'skipped' => 0];
        $this->skippedInvoices = [];
        $this->skippedLines = [];
        $this->skippedPayments = [];
    }

    protected function loadCsvFiles(): void
    {
        $required = [
            'clientes.csv',
            'facturas.csv',
            'lineas_factura.csv',
        ];

        foreach ($required as $filename) {
            $path = $this->importPath.'/'.$filename;

            if (! is_file($path)) {
                throw new InvalidArgumentException("Falta el archivo requerido: {$filename} (raíz del proyecto)");
            }
        }

        $this->customerRows = LegacyCsvReader::read($this->importPath.'/clientes.csv');
        $this->invoiceRows = LegacyCsvReader::read($this->importPath.'/facturas.csv');
        $this->lineRows = LegacyCsvReader::read($this->importPath.'/lineas_factura.csv');

        $pagosPath = $this->importPath.'/pagos.csv';
        $this->paymentRows = is_file($pagosPath)
            ? LegacyCsvReader::read($pagosPath)
            : [];
    }

    protected function preloadLookups(): void
    {
        $this->productIdsBySku = Product::query()
            ->pluck('id', 'sku')
            ->all();

        $this->paymentMethodsBySlug = PaymentMethod::query()
            ->get()
            ->keyBy('slug')
            ->all();

        foreach (Customer::query()->get() as $customer) {
            if ($customer->legacy_id !== null) {
                $this->customersByLegacyId[(string) $customer->legacy_id] = $customer;
            }

            if ($customer->hubspot_company_id !== null) {
                $this->customersByLegacyId[(string) $customer->hubspot_company_id] = $customer;
            }

            $this->customersByCommercialName[$this->normalizeName($customer->name)] = $customer;
        }

        foreach (Invoice::query()->get() as $invoice) {
            if ($invoice->legacy_invoice_number !== null) {
                $this->invoicesByLegacyId[$invoice->legacy_invoice_number] = $invoice;
            }
        }
    }

    protected function importCustomers(): void
    {
        foreach ($this->customerRows as $entry) {
            $row = $entry['row'];
            $data = $entry['data'];

            $legacyId = $this->legacyIdFromCsv(LegacyCsvReader::value($data, 'cliente_id'));
            $commercialName = LegacyCsvReader::value($data, 'nombre_comercial');
            $fiscalName = LegacyCsvReader::value($data, 'nombre_fiscal');
            $name = $commercialName ?: $fiscalName;

            if ($legacyId === null && blank($name)) {
                $this->customerStats['skipped']++;

                throw new InvalidArgumentException("Fila {$row} en clientes.csv: falta cliente_id y nombre.");
            }

            $taxId = $this->normalizeTaxId(LegacyCsvReader::value($data, 'nif_cif'));
            $hubspotId = LegacyCsvReader::value($data, 'hubspot_company_id') ?? $legacyId;

            $existing = $this->findExistingCustomer($taxId, $legacyId);
            $hubspotId = $this->resolveHubspotCompanyId($hubspotId, $existing);

            $attributes = [
                'legacy_id' => $legacyId,
                'name' => $name ?? 'Cliente sin nombre',
                'tax_id' => $taxId,
                'email' => LegacyCsvReader::value($data, 'email'),
                'phone' => LegacyCsvReader::value($data, 'telefono'),
                'address' => LegacyCsvReader::value($data, 'direccion'),
                'city' => LegacyCsvReader::value($data, 'ciudad'),
                'postal_code' => LegacyCsvReader::value($data, 'codigo_postal'),
                'country' => LegacyCsvReader::value($data, 'pais') ?? 'ES',
                'customer_type' => $this->mapCustomerType(LegacyCsvReader::value($data, 'tipo_cliente')),
                'credit_limit' => $this->decimalOrZero(LegacyCsvReader::value($data, 'limite_credito')),
                'hubspot_company_id' => $hubspotId,
            ];

            $action = $existing === null ? 'created' : 'updated';

            if (! $this->dryRun) {
                $customer = $existing ?? new Customer;
                $customer->fill($attributes);
                $customer->save();
            } else {
                $customer = $existing ?? new Customer($attributes);
                $customer->legacy_id = $legacyId;
                $customer->name = $attributes['name'];
            }

            if ($legacyId !== null) {
                $this->customersByLegacyId[$legacyId] = $customer;
            }

            if (filled($hubspotId)) {
                $this->customersByLegacyId[(string) $hubspotId] = $customer;
            }

            if (filled($name)) {
                $this->customersByCommercialName[$this->normalizeName($name)] = $customer;
            }

            $this->customerStats[$action]++;
        }
    }

    protected function importInvoices(): void
    {
        foreach ($this->invoiceRows as $entry) {
            $row = $entry['row'];
            $data = $entry['data'];

            $legacyInvoiceId = LegacyCsvReader::value($data, 'factura_id');

            if ($this->shouldSkipInvalidInvoiceRow($data)) {
                $this->invoiceStats['skipped']++;
                $this->skippedInvoices[] = "Fila {$row}: fila vacía o inválida — omitida";

                continue;
            }

            $invoiceNumber = LegacyCsvReader::value($data, 'numero_factura')
                ?? ($legacyInvoiceId !== null ? 'LEGACY-'.$legacyInvoiceId : null);
            $estado = Str::lower((string) LegacyCsvReader::value($data, 'estado'));

            if ($this->shouldSkipCancelledInvoiceRow($data)) {
                $this->invoiceStats['skipped']++;
                $label = $invoiceNumber ?? $legacyInvoiceId ?? '(sin id)';
                $this->skippedInvoices[] = "Fila {$row}: factura cancelada ({$label}) — omitida";

                continue;
            }

            if (blank($invoiceNumber)) {
                throw new InvalidArgumentException("Fila {$row} en facturas.csv: falta factura_id / numero_factura.");
            }

            $customer = $this->resolveCustomerForInvoice($data, $row);

            if ($customer === null) {
                $this->invoiceStats['skipped']++;
                $commercialName = LegacyCsvReader::value($data, 'nombre_comercial') ?? '(vacío)';
                $clienteId = LegacyCsvReader::value($data, 'cliente_id') ?? '(vacío)';
                $this->skippedInvoices[] = "Fila {$row}: sin cliente (cliente_id={$clienteId}, nombre_comercial={$commercialName}) — factura {$invoiceNumber} omitida";

                continue;
            }

            $issuedAt = LegacyDateParser::parse(
                LegacyCsvReader::value($data, 'fecha'),
                'fecha',
                $row,
            );

            $attributes = [
                'customer_id' => $customer->id ?? 0,
                'order_id' => null,
                'invoice_number' => $invoiceNumber,
                'legacy_invoice_number' => $legacyInvoiceId,
                'document_type' => 'invoice',
                'status' => $this->mapInvoiceStatus($estado),
                'total_amount' => $this->decimalOrZero(LegacyCsvReader::value($data, 'total_factura')),
                'generates_stock_movement' => false,
                'stock_movements_recorded' => false,
                'issued_at' => $issuedAt,
                'cancelled_at' => null,
            ];

            $existing = $this->findExistingInvoice($invoiceNumber, $legacyInvoiceId);
            $action = $existing === null ? 'created' : 'updated';

            if (! $this->dryRun) {
                $invoice = $existing ?? new Invoice;
                $invoice->fill($attributes);
                $invoice->save();
            } else {
                $invoice = $existing ?? new Invoice($attributes);
                $invoice->invoice_number = $invoiceNumber;
                $invoice->legacy_invoice_number = $legacyInvoiceId;
            }

            if ($legacyInvoiceId !== null) {
                $this->invoicesByLegacyId[$legacyInvoiceId] = $invoice;
            }

            $this->invoiceStats[$action]++;
        }
    }

    protected function importInvoiceLines(): void
    {
        foreach ($this->lineRows as $entry) {
            $row = $entry['row'];
            $data = $entry['data'];
            $legacyInvoiceId = LegacyCsvReader::value($data, 'factura_id');

            if (blank($legacyInvoiceId) || $this->isExcelErrorValue($legacyInvoiceId)) {
                $this->lineStats['skipped']++;
                $this->skippedLines[] = "Fila {$row}: línea inválida — omitida";

                continue;
            }

            $invoice = $this->invoicesByLegacyId[$legacyInvoiceId] ?? null;

            if ($invoice === null) {
                $this->lineStats['skipped']++;
                $this->skippedLines[] = "Fila {$row}: factura_id {$legacyInvoiceId} no importada — línea omitida";

                continue;
            }

            $legacyLineId = $row;
            $sku = LegacyCsvReader::value($data, 'sku');
            $cantidadRaw = LegacyCsvReader::value($data, 'cantidad');

            if ($cantidadRaw === null || $this->isExcelErrorValue($cantidadRaw)) {
                $this->lineStats['skipped']++;
                $this->skippedLines[] = "Fila {$row}: cantidad inválida — línea omitida";

                continue;
            }

            $quantity = $this->intOrNull($cantidadRaw);

            if ($quantity === null || $quantity === 0) {
                throw new InvalidArgumentException("Fila {$row} en lineas_factura.csv: cantidad inválida.");
            }

            $attributes = [
                'invoice_id' => $invoice->id ?? 0,
                'legacy_line_id' => $legacyLineId,
                'product_id' => $sku !== null ? ($this->productIdsBySku[$sku] ?? null) : null,
                'description' => LegacyCsvReader::value($data, 'descripcion') ?? ($sku ?? 'Línea importada'),
                'quantity' => $quantity,
                'unit_price' => $this->decimalOrZero(LegacyCsvReader::value($data, 'precio_unitario')),
                'discount_percent' => $this->decimalOrZero(LegacyCsvReader::value($data, 'descuento')),
                'total_price' => $this->decimalOrZero(LegacyCsvReader::value($data, 'subtotal')),
            ];

            $existing = null;

            if (! $this->dryRun && $invoice->id) {
                $existing = InvoiceItem::query()
                    ->where('invoice_id', $invoice->id)
                    ->where('legacy_line_id', $legacyLineId)
                    ->first();
            }

            $action = $existing === null ? 'created' : 'updated';

            if (! $this->dryRun) {
                $item = $existing ?? new InvoiceItem;
                $item->fill($attributes);
                $item->save();
            }

            $this->lineStats[$action]++;
        }
    }

    protected function importPayments(): void
    {
        foreach ($this->paymentRows as $entry) {
            $row = $entry['row'];
            $data = $entry['data'];

            $legacyPaymentId = $this->intOrNull(LegacyCsvReader::value($data, 'pago_id')) ?? $row;
            $legacyInvoiceId = LegacyCsvReader::value($data, 'factura_id');
            $amount = $this->decimalOrZero(LegacyCsvReader::value($data, 'importe'));

            if ($amount <= 0) {
                throw new InvalidArgumentException("Fila {$row} en pagos.csv: importe inválido.");
            }

            $customer = $this->resolveCustomerForPayment($data, $row, $legacyInvoiceId);

            if ($customer === null) {
                $this->paymentStats['skipped']++;
                $this->skippedPayments[] = "Fila {$row}: sin cliente — pago omitido";

                continue;
            }

            $invoice = $legacyInvoiceId !== null
                ? ($this->invoicesByLegacyId[$legacyInvoiceId] ?? null)
                : null;

            if ($legacyInvoiceId !== null && $invoice === null) {
                $this->paymentStats['skipped']++;
                $this->skippedPayments[] = "Fila {$row}: factura_id {$legacyInvoiceId} no importada — pago omitido";

                continue;
            }

            $paidAt = LegacyDateParser::parse(
                LegacyCsvReader::value($data, 'fecha_pago'),
                'fecha_pago',
                $row,
            ) ?? now();

            $paymentMethod = $this->resolvePaymentMethod(LegacyCsvReader::value($data, 'forma_pago'));

            $existing = $this->dryRun
                ? null
                : Payment::query()->where('legacy_payment_id', $legacyPaymentId)->first();

            $action = $existing === null ? 'created' : 'updated';

            if (! $this->dryRun) {
                if ($existing !== null) {
                    $existing->fill([
                        'customer_id' => $customer->id,
                        'invoice_id' => $invoice?->id,
                        'payment_method_id' => $paymentMethod->id,
                        'amount' => $amount,
                        'paid_at' => $paidAt,
                    ]);
                    $existing->save();
                } else {
                    $detail = $this->paymentDetailService->createForMethod(
                        $paymentMethod,
                        $this->legacyPaymentDetailPayload($paymentMethod, $legacyPaymentId),
                    );

                    Payment::query()->create([
                        'legacy_payment_id' => $legacyPaymentId,
                        'customer_id' => $customer->id,
                        'invoice_id' => $invoice?->id,
                        'payment_method_id' => $paymentMethod->id,
                        'detail_type' => $paymentMethod->detail_type,
                        'detail_id' => $detail->id,
                        'amount' => $amount,
                        'paid_at' => $paidAt,
                    ]);
                }
            }

            $this->paymentStats[$action]++;
        }
    }

    protected function rebuildLedgers(): void
    {
        Customer::query()
            ->orderBy('id')
            ->chunkById(50, function ($customers): void {
                foreach ($customers as $customer) {
                    $this->accountStatementService->rebuildLedger($customer);
                }
            });
    }

    /**
     * @return array{customers: array{csv: int, db: int}, invoices: array{csv: int, db: int}, totals: array{csv: float, db: float}}
     */
    protected function buildComparisonTotals(): array
    {
        $legacyInvoiceKeys = collect($this->invoiceRows)
            ->map(fn (array $entry): ?string => LegacyCsvReader::value($entry['data'], 'factura_id'))
            ->filter()
            ->values();

        $invoiceNumbers = collect($this->invoiceRows)
            ->map(function (array $entry): ?string {
                $data = $entry['data'];
                $legacyId = LegacyCsvReader::value($data, 'factura_id');

                return LegacyCsvReader::value($data, 'numero_factura')
                    ?? ($legacyId !== null ? 'LEGACY-'.$legacyId : null);
            })
            ->filter()
            ->values();

        $csvTotalSum = collect($this->invoiceRows)
            ->sum(fn (array $entry): float => $this->decimalOrZero(
                LegacyCsvReader::value($entry['data'], 'total_factura'),
            ));

        $dbInvoiceQuery = Invoice::query()
            ->when(
                $legacyInvoiceKeys->isNotEmpty() || $invoiceNumbers->isNotEmpty(),
                fn ($query) => $query->where(function ($inner) use ($legacyInvoiceKeys, $invoiceNumbers): void {
                    if ($legacyInvoiceKeys->isNotEmpty()) {
                        $inner->whereIn('legacy_invoice_number', $legacyInvoiceKeys);
                    }

                    if ($invoiceNumbers->isNotEmpty()) {
                        $inner->orWhereIn('invoice_number', $invoiceNumbers);
                    }
                }),
            );

        return [
            'customers' => [
                'csv' => count($this->customerRows),
                'db' => Customer::query()->count(),
            ],
            'invoices' => [
                'csv' => count($this->invoiceRows),
                'db' => (clone $dbInvoiceQuery)->count(),
            ],
            'totals' => [
                'csv' => round($csvTotalSum, 2),
                'db' => round((float) (clone $dbInvoiceQuery)->sum('total_amount'), 2),
            ],
        ];
    }

    protected function findExistingCustomer(?string $taxId, ?string $legacyId): ?Customer
    {
        if (filled($taxId)) {
            $match = Customer::query()->where('tax_id', $taxId)->first();

            if ($match !== null) {
                return $match;
            }
        }

        if ($legacyId !== null) {
            $match = Customer::query()->where('legacy_id', $legacyId)->first();

            if ($match !== null) {
                return $match;
            }

            return Customer::query()->where('hubspot_company_id', $legacyId)->first();
        }

        return null;
    }

    protected function findExistingInvoice(string $invoiceNumber, ?string $legacyInvoiceId): ?Invoice
    {
        $match = Invoice::query()->where('invoice_number', $invoiceNumber)->first();

        if ($match !== null) {
            return $match;
        }

        if ($legacyInvoiceId !== null) {
            return Invoice::query()
                ->where('legacy_invoice_number', $legacyInvoiceId)
                ->first();
        }

        return null;
    }

    /**
     * @param  array<string, string>  $data
     */
    protected function resolveCustomerForInvoice(array $data, int $row): ?Customer
    {
        $legacyClientId = $this->legacyIdFromCsv(LegacyCsvReader::value($data, 'cliente_id'));

        if ($legacyClientId !== null && isset($this->customersByLegacyId[$legacyClientId])) {
            return $this->customersByLegacyId[$legacyClientId];
        }

        $commercialName = LegacyCsvReader::value($data, 'nombre_comercial');

        if ($commercialName !== null) {
            $match = $this->customersByCommercialName[$this->normalizeName($commercialName)] ?? null;

            if ($match !== null) {
                return $match;
            }
        }

        if ($legacyClientId !== null) {
            return Customer::query()
                ->where('legacy_id', $legacyClientId)
                ->orWhere('hubspot_company_id', $legacyClientId)
                ->first();
        }

        return null;
    }

    /**
     * @param  array<string, string>  $data
     */
    protected function resolveCustomerForPayment(array $data, int $row, ?string $legacyInvoiceId): ?Customer
    {
        $legacyClientId = $this->legacyIdFromCsv(LegacyCsvReader::value($data, 'cliente_id'));

        if ($legacyClientId !== null && isset($this->customersByLegacyId[$legacyClientId])) {
            return $this->customersByLegacyId[$legacyClientId];
        }

        if ($legacyInvoiceId !== null) {
            $invoice = $this->invoicesByLegacyId[$legacyInvoiceId] ?? null;

            if ($invoice !== null && $invoice->customer_id) {
                return Customer::query()->find($invoice->customer_id);
            }
        }

        $commercialName = LegacyCsvReader::value($data, 'nombre_comercial');

        if ($commercialName !== null) {
            return $this->customersByCommercialName[$this->normalizeName($commercialName)] ?? null;
        }

        return null;
    }

    protected function resolvePaymentMethod(?string $raw): PaymentMethod
    {
        $slug = match (Str::lower((string) $raw)) {
            'transferencia', 'transferencia bancaria', 'bank_transfer' => 'transferencia',
            'efectivo', 'cash' => 'efectivo',
            'tarjeta', 'card' => 'tarjeta',
            'bizum' => 'bizum',
            'cheque' => 'cheque',
            default => 'manual',
        };

        return $this->paymentMethodsBySlug[$slug]
            ?? $this->paymentMethodsBySlug['manual']
            ?? PaymentMethod::query()->where('slug', 'manual')->firstOrFail();
    }

    protected function mapInvoiceStatus(?string $estado): string
    {
        return match (Str::lower((string) $estado)) {
            'pagado', 'paid', 'pago' => 'paid',
            'borrador', 'draft' => 'draft',
            default => 'issued',
        };
    }

    /**
     * @param  array<string, string>  $data
     */
    protected function shouldSkipInvalidInvoiceRow(array $data): bool
    {
        $legacyInvoiceId = LegacyCsvReader::value($data, 'factura_id');

        if ($legacyInvoiceId === null) {
            return true;
        }

        return in_array(Str::upper($legacyInvoiceId), ['#N/A', '#VALUE!', '#REF!', 'N/A'], true);
    }

    protected function isExcelErrorValue(string $value): bool
    {
        return in_array(Str::upper(trim($value)), ['#N/A', '#VALUE!', '#REF!', 'N/A'], true);
    }

    protected function shouldSkipCancelledInvoice(?string $estado): bool
    {
        $normalized = Str::lower((string) $estado);

        return in_array($normalized, ['cancelar', 'cancelada', 'cancelado', 'anulada', 'anulado'], true);
    }

    /**
     * @param  array<string, string>  $data
     */
    protected function shouldSkipCancelledInvoiceRow(array $data): bool
    {
        if ($this->shouldSkipCancelledInvoice(LegacyCsvReader::value($data, 'estado'))) {
            return true;
        }

        $fecha = Str::upper(trim((string) LegacyCsvReader::value($data, 'fecha')));
        $facturaId = Str::upper(trim((string) LegacyCsvReader::value($data, 'factura_id')));

        return in_array($fecha, ['CANCELAR', 'CANCELADA', 'ANULAR', 'ANULADA'], true)
            || in_array($facturaId, ['CANCELAR', 'CANCELADA'], true);
    }

    protected function mapCustomerType(?string $raw): string
    {
        return match (Str::lower((string) $raw)) {
            'individual', 'particular', 'retail' => 'individual',
            default => 'horeca',
        };
    }

    protected function normalizeTaxId(?string $taxId): ?string
    {
        if (blank($taxId)) {
            return null;
        }

        return Str::upper(trim($taxId));
    }

    protected function normalizeName(string $name): string
    {
        return Str::upper(trim($name));
    }

    protected function intOrNull(?string $value): ?int
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return (int) $value;
    }

    protected function legacyIdFromCsv(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return trim($value);
    }

    protected function resolveHubspotCompanyId(?string $hubspotId, ?Customer $existing): ?string
    {
        if (blank($hubspotId)) {
            return $existing?->hubspot_company_id;
        }

        $owner = Customer::query()->where('hubspot_company_id', $hubspotId)->first();

        if ($owner === null) {
            return $hubspotId;
        }

        if ($existing !== null && (int) $owner->id === (int) $existing->id) {
            return $hubspotId;
        }

        return $existing?->hubspot_company_id;
    }

    protected function decimalOrZero(?string $value): float
    {
        if ($value === null || trim($value) === '') {
            return 0.0;
        }

        $normalized = str_replace([' ', ','], ['', '.'], trim($value));

        return round((float) $normalized, 2);
    }

    /**
     * @return array<string, mixed>
     */
    protected function legacyPaymentDetailPayload(PaymentMethod $paymentMethod, int $legacyPaymentId): array
    {
        return match ($paymentMethod->detail_type) {
            'bank_transfer' => ['transaction_number' => 'LEGACY-'.$legacyPaymentId],
            'cheque' => ['cheque_number' => 'LEGACY-'.$legacyPaymentId],
            default => ['notes' => 'Importado legacy pago '.$legacyPaymentId],
        };
    }
}

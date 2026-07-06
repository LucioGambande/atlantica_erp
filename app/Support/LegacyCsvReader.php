<?php

namespace App\Support;

use InvalidArgumentException;
use RuntimeException;

class LegacyCsvReader
{
    /**
     * @return list<array{row: int, data: array<string, string>}>
     */
    public static function read(string $path): array
    {
        if (! is_readable($path)) {
            throw new InvalidArgumentException("No se puede leer el archivo CSV: {$path}");
        }

        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new RuntimeException("No se pudo abrir el archivo CSV: {$path}");
        }

        $headers = null;
        $rows = [];
        $lineNumber = 0;

        while (($raw = fgetcsv($handle)) !== false) {
            $lineNumber++;

            if ($raw === [null] || $raw === []) {
                continue;
            }

            $raw = array_map(
                fn (?string $value): string => trim((string) ($value ?? '')),
                $raw,
            );

            if ($headers === null) {
                $headers = array_map([self::class, 'normalizeHeader'], $raw);

                continue;
            }

            if (self::isBlankRow($raw)) {
                continue;
            }

            $data = [];

            foreach ($headers as $index => $header) {
                if ($header === '') {
                    continue;
                }

                $data[$header] = $raw[$index] ?? '';
            }

            $rows[] = [
                'row' => $lineNumber,
                'data' => $data,
            ];
        }

        fclose($handle);

        return $rows;
    }

    public static function normalizeHeader(string $header): string
    {
        $header = trim($header);
        $header = preg_replace('/^\xEF\xBB\xBF/', '', $header) ?? $header;
        $header = mb_strtolower($header);
        $header = str_replace([' ', '-', '.'], '_', $header);
        $header = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $header) ?: $header;
        $header = preg_replace('/[^a-z0-9_]/', '', $header) ?? $header;

        return match ($header) {
            'nif', 'cif', 'nifcif' => 'nif_cif',
            'nombre', 'razon_social', 'nombre_fiscal' => 'nombre_comercial',
            'numero_factura', 'num_factura', 'invoice_number' => 'numero_factura',
            'total', 'importe_total' => 'total_factura',
            'fecha', 'fecha_emision', 'fecha_factura', 'issued_at' => 'fecha',
            'estado', 'status' => 'estado',
            'linea', 'linea_factura_id', 'linea_id' => 'linea_id',
            'descripcion', 'producto', 'concepto' => 'descripcion',
            'cantidad', 'qty' => 'cantidad',
            'precio', 'precio_unit', 'pvp' => 'precio_unitario',
            'descuento', 'descuento_pct', 'discount' => 'descuento',
            'subtotal', 'total_linea', 'importe_linea' => 'subtotal',
            'pago', 'pago_id' => 'pago_id',
            'importe', 'monto' => 'importe',
            'fecha_pago', 'paid_at' => 'fecha_pago',
            'forma_pago', 'metodo_pago', 'payment_method' => 'forma_pago',
            'hubspot', 'hubspot_company_id', 'hubspot_id' => 'hubspot_company_id',
            'telefono', 'phone' => 'telefono',
            'tipo', 'tipo_cliente', 'customer_type' => 'tipo_cliente',
            'limite', 'limite_credito', 'credit_limit' => 'limite_credito',
            default => $header,
        };
    }

    /**
     * @param  list<string>  $values
     */
    private static function isBlankRow(array $values): bool
    {
        foreach ($values as $value) {
            if (trim($value) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, string>  $row
     */
    public static function value(array $row, string ...$keys): ?string
    {
        foreach ($keys as $key) {
            $normalized = self::normalizeHeader($key);
            $value = trim($row[$normalized] ?? '');

            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }
}

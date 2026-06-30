<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Impresión de facturas</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 12px;
            color: #111;
            background: #f3f4f6;
        }
        .toolbar {
            position: sticky;
            top: 0;
            z-index: 10;
            display: flex;
            gap: 12px;
            align-items: center;
            padding: 12px 20px;
            background: #1e3a5f;
            color: #fff;
        }
        .toolbar button {
            border: 0;
            background: #fff;
            color: #1e3a5f;
            padding: 8px 16px;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
        }
        .page {
            width: 210mm;
            min-height: 297mm;
            margin: 20px auto;
            padding: 18mm 16mm;
            background: #fff;
            box-shadow: 0 2px 16px rgb(0 0 0 / 0.12);
        }
        .title {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 18px;
        }
        .meta {
            margin-bottom: 18px;
            line-height: 1.7;
        }
        .parties {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin: 24px 0;
        }
        .party h3 {
            margin: 0 0 8px;
            font-size: 13px;
            text-transform: uppercase;
        }
        .party p {
            margin: 0;
            line-height: 1.6;
        }
        table.items {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
        }
        table.items th,
        table.items td {
            border: 1px solid #333;
            padding: 8px 6px;
            text-align: left;
        }
        table.items th {
            background: #f8fafc;
            font-size: 11px;
        }
        table.items td.num {
            text-align: right;
            white-space: nowrap;
        }
        .totals {
            margin-top: 16px;
            display: flex;
            justify-content: flex-end;
        }
        .totals table td {
            padding: 6px 10px;
        }
        .totals table td.num {
            text-align: right;
            font-weight: 700;
        }
        .footer {
            margin-top: 28px;
            line-height: 1.7;
        }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .page {
                margin: 0;
                box-shadow: none;
                page-break-after: always;
            }
            .page:last-child { page-break-after: auto; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <strong>{{ count($documents) }} documento(s)</strong>
        <button type="button" onclick="window.print()">Imprimir</button>
        <button type="button" onclick="window.close()">Cerrar</button>
    </div>

    @foreach ($documents as $document)
        <section class="page">
            <div class="title">{{ $document['title'] }}</div>

            <div class="meta">
                <div><strong>Fecha de factura:</strong> {{ $document['issued_at']->format('d/m/Y') }}</div>
                <div><strong>Número de factura:</strong> {{ $document['invoice_number'] }}</div>
                <div><strong>Fecha de vencimiento:</strong> {{ $document['due_at']->format('d/m/Y') }}</div>
            </div>

            <div class="parties">
                <div class="party">
                    <h3>Emisor</h3>
                    <p>
                        <strong>{{ $document['issuer']['name'] ?? '' }}</strong><br>
                        Dirección: {{ $document['issuer']['address'] ?? '' }}<br>
                        N.I.F.: {{ $document['issuer']['tax_id'] ?? '' }}<br>
                        CP, ciudad: {{ $document['issuer']['postal_code'] ?? '' }}, {{ $document['issuer']['city'] ?? '' }}<br>
                        email: {{ $document['issuer']['email'] ?? '' }}
                    </p>
                </div>
                <div class="party">
                    <h3>Cliente</h3>
                    <p>
                        <strong>{{ $document['customer']['name'] }}</strong><br>
                        @if ($document['customer']['address'])
                            {{ $document['customer']['address'] }}<br>
                        @endif
                        @if ($document['customer']['tax_id'])
                            N.I.F.: {{ $document['customer']['tax_id'] }}<br>
                        @endif
                        @if ($document['customer']['postal_code'] || $document['customer']['city'])
                            CP, ciudad: {{ $document['customer']['postal_code'] }}, {{ $document['customer']['city'] }}<br>
                        @endif
                        @if ($document['customer']['email'])
                            email: {{ $document['customer']['email'] }}
                        @endif
                    </p>
                </div>
            </div>

            <table class="items">
                <thead>
                    <tr>
                        <th>Descripción</th>
                        <th>Unidades</th>
                        <th>Precio unitario</th>
                        <th>IVA</th>
                        <th>Dto. (%)</th>
                        <th>Precio</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($document['lines'] as $line)
                        <tr>
                            <td>{{ $line['description'] }}</td>
                            <td class="num">{{ $line['quantity'] }}</td>
                            <td class="num">{{ number_format($line['unit_price'], 2, ',', '.') }} €</td>
                            <td class="num">{{ number_format($line['vat_rate'] * 100, 0) }}%</td>
                            <td class="num">{{ number_format($line['discount_percent'], 2, ',', '.') }}</td>
                            <td class="num">{{ number_format($line['line_total'], 2, ',', '.') }} €</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">Sin líneas</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <div class="totals">
                <table>
                    <tr>
                        <td>TOTAL</td>
                        <td class="num">{{ number_format($document['total'], 2, ',', '.') }} €</td>
                    </tr>
                </table>
            </div>

            @if ($document['iban'])
                <div class="footer">
                    <strong>IBAN:</strong> {{ $document['iban'] }}
                </div>
            @endif
        </section>
    @endforeach
</body>
</html>

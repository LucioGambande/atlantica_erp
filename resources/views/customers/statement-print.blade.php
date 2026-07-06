<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $document['title'] }} — {{ $document['customer']->name }}</title>
    <style>
        @page {
            margin: 14mm 12mm;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: DejaVu Sans, Arial, Helvetica, sans-serif;
            font-size: 10px;
            color: #111;
            line-height: 1.4;
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
        .toolbar a,
        .toolbar button {
            border: 0;
            background: #fff;
            color: #1e3a5f;
            padding: 8px 16px;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            font-size: 12px;
        }
        .statement-page {
            width: 100%;
        }
        .header-title {
            font-size: 14px;
            font-weight: 700;
            margin: 0 0 4px;
        }
        .logo {
            max-width: 140px;
            max-height: 48px;
        }
        .layout-table,
        .meta-table,
        .summary-table,
        .entries-table {
            width: 100%;
            border-collapse: collapse;
        }
        .layout-table td {
            vertical-align: top;
            padding: 0;
        }
        .meta-table td {
            padding: 2px 0;
            vertical-align: top;
        }
        .meta-label {
            width: 28%;
            font-weight: 700;
        }
        .summary-table td {
            width: 50%;
            vertical-align: top;
            padding: 6px 8px 6px 0;
        }
        .summary-box {
            border: 1px solid #d1d5db;
            padding: 8px 10px;
        }
        .summary-label {
            font-size: 8px;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        .summary-value {
            font-size: 12px;
            font-weight: 700;
            margin-top: 3px;
        }
        .entries-table {
            table-layout: fixed;
            margin-top: 12px;
            font-size: 9px;
        }
        .entries-table th,
        .entries-table td {
            border-bottom: 1px solid #e5e7eb;
            padding: 5px 4px;
            vertical-align: top;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        .entries-table th {
            background: #f9fafb;
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            text-align: left;
        }
        .col-date { width: 11%; }
        .col-type { width: 10%; }
        .col-desc { width: 34%; }
        .col-money { width: 15%; }
        .text-right { text-align: right; }
        .text-danger { color: #b91c1c; }
        .text-success { color: #15803d; }
        .num { white-space: nowrap; }
        @media screen {
            body { background: #f3f4f6; }
            .statement-page {
                max-width: 210mm;
                margin: 20px auto;
                padding: 12mm;
                background: #fff;
                box-shadow: 0 2px 16px rgb(0 0 0 / 0.12);
            }
        }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .statement-page {
                margin: 0;
                padding: 0;
                box-shadow: none;
                max-width: none;
            }
        }
    </style>
</head>
<body>
    @if ($pdfUrl ?? null)
        <div class="toolbar">
            <strong>{{ $document['title'] }} — {{ $document['customer']->name }}</strong>
            <a href="{{ $pdfUrl }}" target="_blank">Descargar PDF</a>
            <button type="button" onclick="window.print()">Imprimir</button>
            <button type="button" onclick="window.close()">Cerrar</button>
        </div>
    @endif

    <div class="statement-page">
        <table class="layout-table" style="margin-bottom: 12px;">
            <tr>
                <td style="width: 55%;">
                    @if ($logoBase64 ?? null)
                        <img class="logo" src="data:image/png;base64,{{ $logoBase64 }}" alt="Atlántica Terranova">
                    @endif
                </td>
                <td style="width: 45%; text-align: right;">
                    <div class="header-title">{{ $document['title'] }}</div>
                    <div>Generado: {{ $document['generated_at']->format('d/m/Y H:i') }}</div>
                </td>
            </tr>
        </table>

        <table class="meta-table" style="margin-bottom: 12px;">
            <tr>
                <td class="meta-label">Cliente</td>
                <td>{{ $document['customer']->name }}</td>
            </tr>
            @if (filled($document['customer']->tax_id))
                <tr>
                    <td class="meta-label">NIF/CIF</td>
                    <td>{{ $document['customer']->tax_id }}</td>
                </tr>
            @endif
            <tr>
                <td class="meta-label">Período</td>
                <td>{{ $document['period_label'] }}</td>
            </tr>
            <tr>
                <td class="meta-label">Movimientos</td>
                <td>{{ $document['type_label'] }}</td>
            </tr>
        </table>

        <table class="summary-table" style="margin-bottom: 12px;">
            <tr>
                <td>
                    <div class="summary-box">
                        <div class="summary-label">Total facturado</div>
                        <div class="summary-value">{{ number_format($document['summary']['total_invoiced'], 2, ',', '.') }} €</div>
                    </div>
                </td>
                <td>
                    <div class="summary-box">
                        <div class="summary-label">Total cobrado</div>
                        <div class="summary-value text-success">{{ number_format($document['summary']['total_paid'], 2, ',', '.') }} €</div>
                    </div>
                </td>
            </tr>
            <tr>
                <td>
                    <div class="summary-box">
                        <div class="summary-label">Saldo pendiente</div>
                        <div class="summary-value {{ $document['summary']['balance_due'] > 0 ? 'text-danger' : 'text-success' }}">
                            {{ number_format($document['summary']['balance_due'], 2, ',', '.') }} €
                        </div>
                    </div>
                </td>
                <td>
                    <div class="summary-box">
                        <div class="summary-label">Saldo final del período</div>
                        <div class="summary-value">{{ number_format($document['summary']['final_balance'], 2, ',', '.') }} €</div>
                    </div>
                </td>
            </tr>
        </table>

        <table class="entries-table">
            <colgroup>
                <col class="col-date">
                <col class="col-type">
                <col class="col-desc">
                <col class="col-money">
                <col class="col-money">
                <col class="col-money">
            </colgroup>
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Tipo</th>
                    <th>Descripción</th>
                    <th class="text-right">Débito</th>
                    <th class="text-right">Crédito</th>
                    <th class="text-right">Saldo</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($document['entries'] as $entry)
                    <tr>
                        <td class="num">{{ $entry->date?->format('d/m/Y') }}</td>
                        <td>{{ $entry->typeLabel() }}</td>
                        <td>{{ $entry->description }}</td>
                        <td class="text-right text-danger num">
                            {{ (float) $entry->debit > 0 ? number_format($entry->debit, 2, ',', '.').' €' : '—' }}
                        </td>
                        <td class="text-right text-success num">
                            {{ (float) $entry->credit > 0 ? number_format($entry->credit, 2, ',', '.').' €' : '—' }}
                        </td>
                        <td class="text-right num">{{ number_format($entry->running_balance, 2, ',', '.') }} €</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">No hay movimientos en el período seleccionado.</td>
                    </tr>
                @endforelse
            </tbody>
            @if ($document['entries']->isNotEmpty())
                <tfoot>
                    <tr>
                        <th colspan="3" class="text-right">Totales del período</th>
                        <th class="text-right text-danger num">{{ number_format($document['summary']['total_debit'], 2, ',', '.') }} €</th>
                        <th class="text-right text-success num">{{ number_format($document['summary']['total_credit'], 2, ',', '.') }} €</th>
                        <th class="text-right num">{{ number_format($document['summary']['final_balance'], 2, ',', '.') }} €</th>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
</body>
</html>

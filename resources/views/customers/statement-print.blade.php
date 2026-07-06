<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $document['title'] }} — {{ $document['customer']->name }}</title>
    @include('invoices.partials.document-styles')
    <style>
        body { background: #f3f4f6; }
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
        .sheet {
            width: 210mm;
            min-height: 297mm;
            margin: 20px auto;
            padding: 18mm 16mm;
            background: #fff;
            box-shadow: 0 2px 16px rgb(0 0 0 / 0.12);
        }
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            margin: 16px 0 24px;
        }
        .summary-box {
            border: 1px solid #d1d5db;
            border-radius: 6px;
            padding: 10px 12px;
        }
        .summary-label {
            font-size: 10px;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .summary-value {
            font-size: 14px;
            font-weight: 700;
            margin-top: 4px;
        }
        .entries-table th,
        .entries-table td {
            border-bottom: 1px solid #e5e7eb;
            padding: 8px 6px;
            text-align: left;
            vertical-align: top;
        }
        .entries-table th {
            background: #f9fafb;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .text-right { text-align: right; }
        .text-danger { color: #b91c1c; }
        .text-success { color: #15803d; }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet {
                margin: 0;
                box-shadow: none;
                padding: 12mm 10mm;
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

    <div class="sheet">
        <table class="parties-table" style="margin-bottom: 16px;">
            <tr>
                <td>
                    @if ($logoBase64 ?? null)
                        <img class="logo" src="data:image/png;base64,{{ $logoBase64 }}" alt="Atlántica Terranova">
                    @endif
                </td>
                <td style="text-align: right;">
                    <div class="header-title">{{ $document['title'] }}</div>
                    <div>Generado: {{ $document['generated_at']->format('d/m/Y H:i') }}</div>
                </td>
            </tr>
        </table>

        <table class="meta-table" style="margin-bottom: 16px;">
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

        <div class="summary-grid">
            <div class="summary-box">
                <div class="summary-label">Total facturado</div>
                <div class="summary-value">{{ number_format($document['summary']['total_invoiced'], 2, ',', '.') }} €</div>
            </div>
            <div class="summary-box">
                <div class="summary-label">Total cobrado</div>
                <div class="summary-value text-success">{{ number_format($document['summary']['total_paid'], 2, ',', '.') }} €</div>
            </div>
            <div class="summary-box">
                <div class="summary-label">Saldo pendiente</div>
                <div class="summary-value {{ $document['summary']['balance_due'] > 0 ? 'text-danger' : 'text-success' }}">
                    {{ number_format($document['summary']['balance_due'], 2, ',', '.') }} €
                </div>
            </div>
            <div class="summary-box">
                <div class="summary-label">Saldo final del período</div>
                <div class="summary-value">{{ number_format($document['summary']['final_balance'], 2, ',', '.') }} €</div>
            </div>
        </div>

        <table class="entries-table" style="width: 100%; border-collapse: collapse;">
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
                        <td>{{ $entry->date?->format('d/m/Y') }}</td>
                        <td>{{ $entry->typeLabel() }}</td>
                        <td>{{ $entry->description }}</td>
                        <td class="text-right text-danger">
                            {{ (float) $entry->debit > 0 ? number_format($entry->debit, 2, ',', '.').' €' : '—' }}
                        </td>
                        <td class="text-right text-success">
                            {{ (float) $entry->credit > 0 ? number_format($entry->credit, 2, ',', '.').' €' : '—' }}
                        </td>
                        <td class="text-right">{{ number_format($entry->running_balance, 2, ',', '.') }} €</td>
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
                        <th class="text-right text-danger">{{ number_format($document['summary']['total_debit'], 2, ',', '.') }} €</th>
                        <th class="text-right text-success">{{ number_format($document['summary']['total_credit'], 2, ',', '.') }} €</th>
                        <th class="text-right">{{ number_format($document['summary']['final_balance'], 2, ',', '.') }} €</th>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
</body>
</html>

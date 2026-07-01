<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Impresión de facturas</title>
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
            page-break-after: always;
        }
        .sheet:last-child { page-break-after: auto; }
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
    <div class="toolbar">
        <strong>{{ count($documents) }} documento(s)</strong>
        @if ($pdfUrl ?? null)
            <a href="{{ $pdfUrl }}" target="_blank">Descargar PDF</a>
        @endif
        <button type="button" onclick="window.print()">Imprimir</button>
        <button type="button" onclick="window.close()">Cerrar</button>
    </div>

    @foreach ($documents as $document)
        <div class="sheet">
            @include('invoices.partials.document', ['document' => $document, 'logoBase64' => $logoBase64])
        </div>
    @endforeach
</body>
</html>

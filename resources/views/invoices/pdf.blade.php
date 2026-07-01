<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Factura</title>
    @include('invoices.partials.document-styles')
    <style>
        .invoice-page { page-break-after: always; }
        .invoice-page:last-child { page-break-after: auto; }
    </style>
</head>
<body>
    @foreach ($documents as $document)
        @include('invoices.partials.document', ['document' => $document, 'logoBase64' => $logoBase64])
    @endforeach
</body>
</html>

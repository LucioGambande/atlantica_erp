<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Albarán</title>
    @include('invoices.partials.document-styles')
</head>
<body>
    @include('orders.partials.document', ['document' => $document, 'logoBase64' => $logoBase64])
</body>
</html>

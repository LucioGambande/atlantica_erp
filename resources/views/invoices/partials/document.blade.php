@php
    $formatMoney = static fn (float $amount): string => number_format($amount, 2, ',', '.').' €';
    $formatPercent = static fn (float $amount): string => number_format($amount, 2, ',', '.');
@endphp

<div class="invoice-page">
    <table width="100%" cellspacing="0" cellpadding="0">
        <tr>
            <td valign="top">
                <div class="header-title">{{ $document['title'] }}</div>
            </td>
            <td align="right" valign="top">
                @if ($logoBase64)
                    <img
                        src="data:image/png;base64,{{ $logoBase64 }}"
                        alt="Atlántica Terranova"
                        class="logo"
                    >
                @endif
            </td>
        </tr>
    </table>

    <table class="meta-table" cellspacing="0" cellpadding="0">
        <tr>
            <td class="meta-label">Fecha de factura:</td>
            <td>{{ $document['issued_at']->format('d/m/Y') }}</td>
        </tr>
        <tr>
            <td class="meta-label">Número de factura:</td>
            <td>{{ $document['invoice_number'] }}</td>
        </tr>
        <tr>
            <td class="meta-label">Fecha de vencimiento:</td>
            <td>{{ $document['due_at']->format('d/m/Y') }}</td>
        </tr>
    </table>

    <table class="parties-table" cellspacing="0" cellpadding="0">
        <tr>
            <td>
                <div class="party-name">{{ $document['issuer']['name'] ?? '' }}</div>
                Dirección: {{ $document['issuer']['address'] ?? '' }}<br>
                N.I.F.: {{ $document['issuer']['tax_id'] ?? '' }}<br>
                CP, ciudad: {{ $document['issuer']['postal_code'] ?? '' }}, {{ $document['issuer']['city'] ?? '' }}<br>
                email: {{ $document['issuer']['email'] ?? '' }}
            </td>
            <td>
                <div class="party-name">{{ $document['customer']['name'] }}</div>
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
            </td>
        </tr>
    </table>

    <table class="items-table">
        <thead>
            <tr>
                <th>Descripción</th>
                <th class="num">Unidades</th>
                <th class="num">Precio unitario</th>
                <th class="num">IVA</th>
                <th class="num">Dto. (%)</th>
                <th class="num">Precio</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($document['lines'] as $line)
                <tr>
                    <td>{{ $line['description'] }}</td>
                    <td class="num">{{ $line['quantity'] }}</td>
                    <td class="num">{{ $formatMoney($line['unit_price']) }}</td>
                    <td class="num">{{ number_format($line['vat_rate'] * 100, 0) }}%</td>
                    <td class="num">{{ $formatPercent($line['discount_percent']) }}</td>
                    <td class="num">{{ $formatMoney($line['line_total']) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6">Sin líneas</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <table class="totals-table" cellspacing="0" cellpadding="0">
        <tr>
            <td class="totals-spacer"></td>
            <td class="totals-box">
                <table width="100%" cellspacing="0" cellpadding="0">
                    <tr>
                        <td class="totals-label">Base imponible</td>
                        <td class="totals-amount">{{ $formatMoney($document['subtotal']) }}</td>
                    </tr>
                    <tr>
                        <td class="totals-label">IVA ({{ number_format($document['vat_rate'] * 100, 0) }}%)</td>
                        <td class="totals-amount">{{ $formatMoney($document['vat_amount']) }}</td>
                    </tr>
                    <tr class="totals-grand">
                        <td class="totals-label">TOTAL</td>
                        <td class="totals-amount">{{ $formatMoney($document['total']) }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    @if ($document['iban'])
        <div class="footer">
            <strong>IBAN:</strong> {{ $document['iban'] }}
        </div>
    @endif
</div>

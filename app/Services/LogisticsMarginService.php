<?php

namespace App\Services;

use App\Models\Invoice;

class LogisticsMarginService
{
    /**
     * Margen de contribución logística de una factura: total facturado neto
     * (sin IVA) menos el coste logístico realmente pagado en los envíos
     * vinculados a esa factura. No incluye costo de mercadería (COGS): el
     * ERP no tiene hoy un costeo confiable (lote opcional en las líneas de
     * factura/pedido), así que ese cálculo queda fuera de alcance.
     *
     * @return array{net: float, logistics_cost: float, margin: float}
     */
    public function forInvoice(Invoice $invoice): array
    {
        $net = $invoice->netAmount();
        $logisticsCost = (float) $invoice->logisticsShipments()->sum('actual_cost');

        return [
            'net' => $net,
            'logistics_cost' => $logisticsCost,
            'margin' => round($net - $logisticsCost, 2),
        ];
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\InvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

/**
 * @group Invoices
 */
class InvoiceController extends Controller
{
    public function __construct(protected InvoiceService $invoiceService) {}

    /**
     * List invoices
     *
     * @queryParam customer_id int Filter by customer id. Example: 211
     * @queryParam customer_name string Filter by customer name (partial match). Example: Vandal
     * @queryParam from date Only invoices issued on or after this date. Example: 2026-01-01
     * @queryParam to date Only invoices issued on or before this date. Example: 2026-12-31
     * @queryParam per_page int Results per page (max 100). Example: 25
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min(100, max(1, (int) $request->integer('per_page', 25)));

        $invoices = $this->invoiceService->paginatedInvoices(
            $request->filled('customer_id') ? $request->integer('customer_id') : null,
            $request->string('customer_name')->value() ?: null,
            $request->string('from')->value() ?: null,
            $request->string('to')->value() ?: null,
            $perPage,
        );

        return response()->json($invoices);
    }

    /**
     * Get an invoice
     */
    public function show(Invoice $invoice): JsonResponse
    {
        $invoice->load(['customer', 'order', 'warehouse', 'invoiceItems.product']);

        return response()->json(['data' => $invoice]);
    }

    /**
     * Cancel an invoice
     *
     * Creates a credit note that reverses the invoice's totals and stock
     * movements, and marks the original invoice as cancelled. The invoice
     * stays in the system; it is not deleted.
     *
     * @response 201 {"message": "Invoice cancelled.", "data": {"id": 399, "document_type": "credit_note", "credited_invoice_id": 371, "total_amount": "-3062.99"}}
     * @response 422 {"message": "Esta factura no se puede cancelar."}
     */
    public function cancel(Invoice $invoice): JsonResponse
    {
        try {
            $creditNote = $this->invoiceService->cancelInvoice($invoice);

            return response()->json([
                'message' => 'Invoice cancelled.',
                'data' => $creditNote->load('invoiceItems.product', 'creditedInvoice'),
            ], 201);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    /**
     * Create a partial (or full) credit note
     *
     * Devolución parcial (o total, si se indican todas las cantidades
     * disponibles) de una factura. A diferencia de cancel(), la factura
     * original no queda cancelada.
     *
     * @bodyParam lines array required Lines to credit. Example: [{"invoice_item_id": 905, "quantity": 6}]
     * @bodyParam lines.*.invoice_item_id int required Id of the invoice_items row being returned. Example: 905
     * @bodyParam lines.*.quantity int required Quantity to credit back (must not exceed what remains returnable for that line). Example: 6
     *
     * @response 201 {"message": "Credit note created.", "data": {"id": 402, "document_type": "credit_note", "credited_invoice_id": 371}}
     * @response 422 {"message": "No podés devolver 10 u. de \"Atamisque Malbec\": solo quedan 6 disponibles."}
     */
    public function creditNote(Request $request, Invoice $invoice): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.invoice_item_id' => ['required', 'integer', 'exists:invoice_items,id'],
            'lines.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $creditNote = $this->invoiceService->createPartialCreditNote(
                $invoice,
                $validator->validated()['lines'],
            );

            return response()->json([
                'message' => 'Credit note created.',
                'data' => $creditNote->load('invoiceItems.product', 'creditedInvoice'),
            ], 201);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }
}

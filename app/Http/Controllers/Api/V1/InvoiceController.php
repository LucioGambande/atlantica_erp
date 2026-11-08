<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\InvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function __construct(protected InvoiceService $invoiceService) {}

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

    public function show(Invoice $invoice): JsonResponse
    {
        $invoice->load(['customer', 'order', 'warehouse', 'invoiceItems.product']);

        return response()->json(['data' => $invoice]);
    }
}

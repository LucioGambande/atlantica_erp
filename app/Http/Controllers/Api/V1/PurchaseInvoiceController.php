<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\HandlesApiExceptions;
use App\Http\Controllers\Controller;
use App\Models\PurchaseInvoice;
use App\Services\StockService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Throwable;

class PurchaseInvoiceController extends Controller
{
    use HandlesApiExceptions;

    public function __construct(protected StockService $stockService) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = min(100, max(1, (int) $request->integer('per_page', 25)));

        $purchaseInvoices = PurchaseInvoice::query()
            ->with(['supplier', 'warehouse'])
            ->when($request->filled('supplier_id'), fn (Builder $query) => $query->where('supplier_id', $request->integer('supplier_id')))
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->string('status')))
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json($purchaseInvoices);
    }

    public function show(PurchaseInvoice $purchaseInvoice): JsonResponse
    {
        $purchaseInvoice->load(['supplier', 'warehouse', 'purchaseInvoiceItems.product', 'purchaseInvoiceItems.lot']);

        return response()->json(['data' => $purchaseInvoice]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'document_number' => ['required', 'string', 'max:255', 'unique:purchase_invoices,document_number'],
            'status' => ['nullable', 'in:draft,received,paid'],
            'total_amount' => ['required', 'numeric', 'min:0'],
            'received_at' => ['nullable', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['nullable', 'integer', 'exists:products,id'],
            'items.*.lot_id' => ['nullable', 'integer', 'exists:lots,id'],
            'items.*.description' => ['nullable', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.total_price' => ['required', 'numeric', 'min:0'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        try {
            $purchaseInvoice = DB::transaction(function () use ($data) {
                $purchaseInvoice = PurchaseInvoice::create([
                    'supplier_id' => $data['supplier_id'],
                    'warehouse_id' => $data['warehouse_id'] ?? null,
                    'document_number' => $data['document_number'],
                    'status' => $data['status'] ?? 'draft',
                    'total_amount' => $data['total_amount'],
                    'received_at' => $data['received_at'] ?? null,
                ]);

                foreach ($data['items'] as $item) {
                    $purchaseInvoice->purchaseInvoiceItems()->create($item);
                }

                $this->stockService->syncStockForPurchaseInvoice($purchaseInvoice);

                // syncStockForPurchaseInvoice() actualiza stock_movements_recorded
                // sobre una instancia propia (lockForUpdate + findOrFail), no sobre
                // esta: hay que releer para no devolver un objeto desactualizado.
                return $purchaseInvoice->fresh();
            });

            $purchaseInvoice->load(['supplier', 'warehouse', 'purchaseInvoiceItems.product', 'purchaseInvoiceItems.lot']);

            return response()->json(['data' => $purchaseInvoice], 201);
        } catch (Throwable $exception) {
            return $this->respondToException($exception);
        }
    }

    /**
     * Solo campos de cabecera: las líneas no se editan por API una vez
     * creadas (mismo criterio que Filament: se bloquean cuando ya
     * movieron stock).
     */
    public function update(Request $request, PurchaseInvoice $purchaseInvoice): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'warehouse_id' => ['sometimes', 'nullable', 'integer', 'exists:warehouses,id'],
            'status' => ['sometimes', 'in:draft,received,paid'],
            'total_amount' => ['sometimes', 'numeric', 'min:0'],
            'received_at' => ['sometimes', 'nullable', 'date'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $purchaseInvoice->update($validator->validated());
            $this->stockService->syncStockForPurchaseInvoice($purchaseInvoice);

            $purchaseInvoice = $purchaseInvoice->fresh(['supplier', 'warehouse', 'purchaseInvoiceItems.product', 'purchaseInvoiceItems.lot']);

            return response()->json(['data' => $purchaseInvoice]);
        } catch (Throwable $exception) {
            return $this->respondToException($exception);
        }
    }
}

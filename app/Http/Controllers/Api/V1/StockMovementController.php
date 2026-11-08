<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class StockMovementController extends Controller
{
    /**
     * Solo lectura: un movimiento de stock nunca se crea suelto por API,
     * siempre es efecto de un documento (compra, traslado, factura) a
     * través de StockService.
     */
    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'lot_id' => ['nullable', 'integer', 'exists:lots,id'],
            'type' => ['nullable', 'in:in,out'],
            'reference_type' => ['nullable', 'string'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $perPage = min(100, max(1, (int) $request->integer('per_page', 25)));

        $movements = StockMovement::query()
            ->with(['product', 'lot', 'warehouse'])
            ->when($request->filled('product_id'), fn (Builder $query) => $query->where('product_id', $request->integer('product_id')))
            ->when($request->filled('warehouse_id'), fn (Builder $query) => $query->where('warehouse_id', $request->integer('warehouse_id')))
            ->when($request->filled('lot_id'), fn (Builder $query) => $query->where('lot_id', $request->integer('lot_id')))
            ->when($request->filled('type'), fn (Builder $query) => $query->where('type', $request->string('type')))
            ->when($request->filled('reference_type'), fn (Builder $query) => $query->where('reference_type', $request->string('reference_type')))
            ->when($request->filled('from'), fn (Builder $query) => $query->whereDate('created_at', '>=', $request->string('from')))
            ->when($request->filled('to'), fn (Builder $query) => $query->whereDate('created_at', '<=', $request->string('to')))
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json($movements);
    }

    public function show(StockMovement $stockMovement): JsonResponse
    {
        $stockMovement->load(['product', 'lot', 'warehouse']);

        return response()->json(['data' => $stockMovement]);
    }
}

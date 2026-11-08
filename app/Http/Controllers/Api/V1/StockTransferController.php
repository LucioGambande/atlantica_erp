<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\HandlesApiExceptions;
use App\Http\Controllers\Controller;
use App\Models\StockTransfer;
use App\Services\StockService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Throwable;

class StockTransferController extends Controller
{
    use HandlesApiExceptions;

    public function __construct(protected StockService $stockService) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = min(100, max(1, (int) $request->integer('per_page', 25)));

        $transfers = StockTransfer::query()
            ->with(['originWarehouse', 'destinationWarehouse'])
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->string('status')))
            ->when($request->filled('origin_warehouse_id'), fn (Builder $query) => $query->where('origin_warehouse_id', $request->integer('origin_warehouse_id')))
            ->when($request->filled('destination_warehouse_id'), fn (Builder $query) => $query->where('destination_warehouse_id', $request->integer('destination_warehouse_id')))
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json($transfers);
    }

    public function show(StockTransfer $stockTransfer): JsonResponse
    {
        $stockTransfer->load(['originWarehouse', 'destinationWarehouse', 'stockTransferItems.product', 'stockTransferItems.lot']);

        return response()->json(['data' => $stockTransfer]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'origin_warehouse_id' => ['required', 'integer', 'exists:warehouses,id', 'different:destination_warehouse_id'],
            'destination_warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'status' => ['nullable', 'in:draft,completed,cancelled'],
            'transferred_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.lot_id' => ['nullable', 'integer', 'exists:lots,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        try {
            $transfer = DB::transaction(function () use ($data) {
                $transfer = StockTransfer::create([
                    'origin_warehouse_id' => $data['origin_warehouse_id'],
                    'destination_warehouse_id' => $data['destination_warehouse_id'],
                    'status' => $data['status'] ?? 'draft',
                    'transferred_at' => $data['transferred_at'] ?? null,
                    'notes' => $data['notes'] ?? null,
                ]);

                foreach ($data['items'] as $item) {
                    $transfer->stockTransferItems()->create($item);
                }

                $this->stockService->syncStockForTransfer($transfer);

                return $transfer;
            });

            $transfer->load(['originWarehouse', 'destinationWarehouse', 'stockTransferItems.product', 'stockTransferItems.lot']);

            return response()->json(['data' => $transfer], 201);
        } catch (Throwable $exception) {
            return $this->respondToException($exception);
        }
    }

    /**
     * Solo cabecera: transición de estado (draft/completed/cancelled) y
     * metadata. Las líneas no se editan por API una vez creado el traslado.
     */
    public function update(Request $request, StockTransfer $stockTransfer): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => ['sometimes', 'in:draft,completed,cancelled'],
            'transferred_at' => ['sometimes', 'nullable', 'date'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $stockTransfer->update($validator->validated());
            $this->stockService->syncStockForTransfer($stockTransfer);

            $stockTransfer = $stockTransfer->fresh(['originWarehouse', 'destinationWarehouse', 'stockTransferItems.product', 'stockTransferItems.lot']);

            return response()->json(['data' => $stockTransfer]);
        } catch (Throwable $exception) {
            return $this->respondToException($exception);
        }
    }
}

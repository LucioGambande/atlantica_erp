<?php

namespace App\Http\Controllers;

use App\Services\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class StockController extends Controller
{
    public function __construct(
        protected StockService $stockService,
    ) {}

    public function products(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'search' => ['nullable', 'string', 'max:255'],
            'low_stock' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        return response()->json($this->stockService->paginatedStock(
            search: $validated['search'] ?? null,
            onlyLowStock: (bool) ($validated['low_stock'] ?? false),
            perPage: (int) ($validated['per_page'] ?? 15),
        ));
    }

    public function summary(): JsonResponse
    {
        return response()->json($this->stockService->stockSummary());
    }
}

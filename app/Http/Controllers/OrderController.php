<?php

namespace App\Http\Controllers;

use App\Services\OrderService;
use App\Services\StockService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Throwable;

class OrderController extends Controller
{
    public function __construct(
        protected OrderService $orderService,
        protected StockService $stockService,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'status' => ['nullable', 'in:pending,completed,cancelled'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $order = DB::transaction(function () use ($validator) {
                $order = $this->orderService->createOrder($validator->validated());
                $this->stockService->reduceStockFromOrder($order);

                return $order;
            });

            return response()->json([
                'message' => 'Order created successfully.',
                'data' => $order->fresh(['customer', 'orderItems.product']),
            ], 201);
        } catch (DomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        } catch (Throwable $exception) {
            return response()->json([
                'message' => 'Unable to create order.',
            ], 500);
        }
    }
}

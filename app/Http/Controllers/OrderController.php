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

/**
 * @group Orders
 */
class OrderController extends Controller
{
    public function __construct(
        protected OrderService $orderService,
        protected StockService $stockService,
    ) {}

    /**
     * Create an order
     *
     * Creates a pending (or completed) order with its line items and
     * immediately reduces stock for each product. If `unit_price` is
     * omitted for a line, the price is resolved from the customer's price
     * list (see PriceResolutionService).
     *
     * @bodyParam customer_id int required Id of the customer. Example: 211
     * @bodyParam warehouse_id int Id of the warehouse to deduct stock from. Defaults to the only/first warehouse if omitted. Example: 1
     * @bodyParam status string One of pending, completed, cancelled. Defaults to pending. Example: pending
     * @bodyParam items array required Order lines. Example: [{"product_id": 16, "quantity": 24, "unit_price": 17.69}]
     * @bodyParam items.*.product_id int required Example: 16
     * @bodyParam items.*.quantity int required Example: 24
     * @bodyParam items.*.unit_price numeric Price per unit before discount. Omit to use the customer's price list. Example: 17.69
     * @bodyParam items.*.discount_percent numeric 0-100. Example: 0
     *
     * @response 201 {"message": "Order created successfully.", "data": {"id": 63, "customer_id": 211, "status": "pending", "total_amount": "1256.76"}}
     * @response 422 {"message": "Order items are required."}
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
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

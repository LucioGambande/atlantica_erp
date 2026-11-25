<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
use App\Services\StockService;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * @group Orders
 *
 * `POST /api/v1/orders` is handled by the (reused) App\Http\Controllers\OrderController::store()
 * — see that method's docblock for its parameters.
 */
class OrderController extends Controller
{
    public function __construct(
        protected OrderService $orderService,
        protected StockService $stockService,
    ) {}

    /**
     * List orders
     *
     * @queryParam customer_id int Filter by customer id. Example: 211
     * @queryParam status string Filter by status. Example: pending
     * @queryParam per_page int Results per page (max 100). Example: 25
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min(100, max(1, (int) $request->integer('per_page', 25)));

        $orders = Order::query()
            ->with('customer:id,name,fiscal_name')
            ->when(
                $request->filled('customer_id'),
                fn (Builder $query): Builder => $query->where('customer_id', $request->integer('customer_id')),
            )
            ->when(
                $request->filled('status'),
                fn (Builder $query): Builder => $query->where('status', $request->string('status')->value()),
            )
            ->orderByDesc('ordered_at')
            ->paginate($perPage);

        return response()->json($orders);
    }

    /**
     * Get an order
     */
    public function show(Order $order): JsonResponse
    {
        $order->load(['customer', 'warehouse', 'orderItems.product', 'invoice']);

        return response()->json(['data' => $order]);
    }

    /**
     * Add an item to an existing order
     *
     * Only allowed while the order has no invoice yet and isn't cancelled.
     * Reduces stock for the added line the same way order creation does.
     * Set `allow_negative_stock` to bypass the stock check (e.g. a product
     * that hasn't received its first purchase invoice yet).
     *
     * @bodyParam product_id int required Example: 35
     * @bodyParam quantity int required Example: 2
     * @bodyParam unit_price numeric Price per unit before discount. Omit to use the customer's price list. Example: 25
     * @bodyParam discount_percent numeric 0-100. Example: 0
     * @bodyParam allow_negative_stock boolean Skip the stock availability check. Defaults to false. Example: false
     */
    public function addItem(Request $request, Order $order): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'unit_price' => ['nullable', 'numeric', 'min:0'],
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'allow_negative_stock' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $allowNegativeStock = (bool) ($data['allow_negative_stock'] ?? false);

        try {
            DB::transaction(function () use ($order, $data, $allowNegativeStock): void {
                $orderItem = $this->orderService->addItem($order, $data);
                $this->stockService->reduceStockForNewOrderItem($orderItem, ! $allowNegativeStock);
            });

            return response()->json([
                'message' => 'Item added to order.',
                'data' => $order->fresh(['customer', 'orderItems.product']),
            ]);
        } catch (DomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        } catch (Throwable $exception) {
            return response()->json(['message' => 'Unable to add item to order.'], 500);
        }
    }
}

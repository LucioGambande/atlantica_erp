<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Orders
 *
 * `POST /api/v1/orders` is handled by the (reused) App\Http\Controllers\OrderController::store()
 * — see that method's docblock for its parameters.
 */
class OrderController extends Controller
{
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
}

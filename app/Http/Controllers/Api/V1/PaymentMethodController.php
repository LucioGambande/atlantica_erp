<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use Illuminate\Http\JsonResponse;

/**
 * @group Payments
 */
class PaymentMethodController extends Controller
{
    /**
     * List active payment methods
     *
     * Use the returned `id` as `payment_method_id` when registering a
     * payment via `POST /api/v1/payments`.
     */
    public function index(): JsonResponse
    {
        $methods = PaymentMethod::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'detail_type', 'sort_order']);

        return response()->json(['data' => $methods]);
    }
}

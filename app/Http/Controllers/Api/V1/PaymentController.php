<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Payments
 *
 * `POST /api/v1/payments` is handled by the (reused) App\Http\Controllers\PaymentController::store()
 * — see that method's docblock for its parameters.
 */
class PaymentController extends Controller
{
    /**
     * List payments
     *
     * @queryParam customer_id int Filter by customer id. Example: 211
     * @queryParam invoice_id int Filter by invoice id. Example: 371
     * @queryParam per_page int Results per page (max 100). Example: 25
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min(100, max(1, (int) $request->integer('per_page', 25)));

        $payments = Payment::query()
            ->with(['customer:id,name,fiscal_name', 'invoice:id,invoice_number', 'paymentMethod:id,name'])
            ->when(
                $request->filled('customer_id'),
                fn (Builder $query): Builder => $query->where('customer_id', $request->integer('customer_id')),
            )
            ->when(
                $request->filled('invoice_id'),
                fn (Builder $query): Builder => $query->where('invoice_id', $request->integer('invoice_id')),
            )
            ->orderByDesc('paid_at')
            ->paginate($perPage);

        return response()->json($payments);
    }

    /**
     * Get a payment
     */
    public function show(Payment $payment): JsonResponse
    {
        $payment->load(['customer', 'invoice', 'paymentMethod', 'detail', 'allocations.invoice:id,invoice_number']);

        return response()->json(['data' => $payment]);
    }
}

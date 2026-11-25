<?php

namespace App\Http\Controllers;

use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use Throwable;

/**
 * @group Payments
 */
class PaymentController extends Controller
{
    public function __construct(
        protected PaymentService $paymentService,
    ) {}

    /**
     * Register a payment
     *
     * Registers a payment and, unless `allocations` is given, applies the
     * full amount to `invoice_id`. Use `GET /api/v1/payment-methods` to get
     * valid `payment_method_id` values; `detail` fields depend on the
     * method's `detail_type` (bank_transfer, card, cash, bizum, cheque,
     * generic) — see App\Support\PaymentDetailType.
     *
     * @bodyParam customer_id int required Example: 211
     * @bodyParam invoice_id int Invoice to apply the full amount to (ignored if `allocations` is given). Example: 371
     * @bodyParam amount numeric required Must be greater than 0. Example: 1274.64
     * @bodyParam payment_method_id int required See GET /api/v1/payment-methods. Example: 1
     * @bodyParam detail object Fields depend on the payment method's detail_type. Example: {"notes": "Pago en efectivo"}
     * @bodyParam paid_at date required Example: 2026-11-24 10:00:00
     * @bodyParam allocations array Split the payment across several invoices instead of one. Example: [{"invoice_id": 371, "amount": 600}, {"invoice_id": 372, "amount": 674.64}]
     * @bodyParam allocations.*.invoice_id int required_with:allocations
     * @bodyParam allocations.*.amount numeric required_with:allocations Must be greater than 0.
     *
     * @response 201 {"message": "Payment registered successfully.", "data": {"id": 55, "customer_id": 211, "amount": "1274.64"}}
     * @response 422 {"message": "La forma de pago seleccionada no es válida."}
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'invoice_id' => ['nullable', 'integer', 'exists:invoices,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_method_id' => ['required', 'integer', 'exists:payment_methods,id'],
            'detail' => ['nullable', 'array'],
            'paid_at' => ['required', 'date'],
            'allocations' => ['nullable', 'array'],
            'allocations.*.invoice_id' => ['required_with:allocations', 'integer', 'exists:invoices,id'],
            'allocations.*.amount' => ['required_with:allocations', 'numeric', 'gt:0'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $payment = $this->paymentService->registerPayment($validator->validated());

            return response()->json([
                'message' => 'Payment registered successfully.',
                'data' => $payment,
            ], 201);
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        } catch (Throwable $exception) {
            return response()->json([
                'message' => 'Unable to register payment.',
            ], 500);
        }
    }
}

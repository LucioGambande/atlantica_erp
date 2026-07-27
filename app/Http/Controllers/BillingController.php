<?php

namespace App\Http\Controllers;

use App\Services\InvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Validator as ValidatorContract;

class BillingController extends Controller
{
    public function __construct(
        protected InvoiceService $invoiceService,
    ) {}

    public function invoices(Request $request): JsonResponse
    {
        $validator = $this->makeFilterValidator($request);

        if ($validator->fails()) {
            return $this->invalidResponse($validator);
        }

        $validated = $validator->validated();

        return response()->json($this->invoiceService->paginatedInvoices(
            customerId: $validated['customer_id'] ?? null,
            customerName: $validated['customer'] ?? null,
            from: $validated['from'] ?? null,
            to: $validated['to'] ?? null,
            perPage: (int) ($validated['per_page'] ?? 15),
        ));
    }

    public function summary(Request $request): JsonResponse
    {
        $validator = $this->makeFilterValidator($request);

        if ($validator->fails()) {
            return $this->invalidResponse($validator);
        }

        $validated = $validator->validated();

        return response()->json($this->invoiceService->billingSummary(
            customerId: $validated['customer_id'] ?? null,
            customerName: $validated['customer'] ?? null,
            from: $validated['from'] ?? null,
            to: $validated['to'] ?? null,
        ));
    }

    public function trend(Request $request): JsonResponse
    {
        $validator = $this->makeFilterValidator($request, [
            'group_by' => ['nullable', 'in:day,month'],
        ]);

        if ($validator->fails()) {
            return $this->invalidResponse($validator);
        }

        $validated = $validator->validated();

        return response()->json($this->invoiceService->billingTrend(
            customerId: $validated['customer_id'] ?? null,
            customerName: $validated['customer'] ?? null,
            from: $validated['from'] ?? null,
            to: $validated['to'] ?? null,
            groupBy: $validated['group_by'] ?? 'day',
        ));
    }

    /**
     * @param  array<string, array<int, string>>  $extraRules
     */
    protected function makeFilterValidator(Request $request, array $extraRules = []): ValidatorContract
    {
        return Validator::make($request->all(), [
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'customer' => ['nullable', 'string', 'max:255'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            ...$extraRules,
        ]);
    }

    protected function invalidResponse(ValidatorContract $validator): JsonResponse
    {
        return response()->json([
            'message' => 'The given data was invalid.',
            'errors' => $validator->errors(),
        ], 422);
    }
}

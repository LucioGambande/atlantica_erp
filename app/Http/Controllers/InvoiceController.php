<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\InvoiceService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use RuntimeException;
use Throwable;

class InvoiceController extends Controller
{
    public function __construct(
        protected InvoiceService $invoiceService,
    ) {}

    public function createFromOrder(int $orderId): JsonResponse
    {
        try {
            $order = Order::query()->findOrFail($orderId);
            $invoice = $this->invoiceService->createFromOrder($order);

            return response()->json([
                'message' => 'Invoice created successfully.',
                'data' => $invoice->fresh(['customer', 'order', 'invoiceItems.product']),
            ], 201);
        } catch (ModelNotFoundException $exception) {
            return response()->json([
                'message' => 'Order not found.',
            ], 404);
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        } catch (Throwable $exception) {
            return response()->json([
                'message' => 'Unable to create invoice.',
            ], 500);
        }
    }
}

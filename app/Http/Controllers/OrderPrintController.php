<?php

namespace App\Http\Controllers;

use App\Services\OrderPrintService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class OrderPrintController extends Controller
{
    public function __construct(
        protected OrderPrintService $printService,
    ) {}

    public function show(Request $request, int $order): Response
    {
        $orderModel = $this->printService->findForPrint($order);
        $document = $this->printService->buildPrintData(
            $orderModel,
            $request->boolean('with_prices', true),
        );

        return Pdf::loadView('orders.pdf', [
            'document' => $document,
            'logoBase64' => $this->printService->logoBase64(),
        ])
            ->setPaper('a4')
            ->stream($this->printService->pdfFilename($orderModel));
    }
}

<?php

namespace App\Http\Controllers;

use App\Services\InvoicePrintService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class InvoicePrintController extends Controller
{
    public function __construct(
        protected InvoicePrintService $printService,
    ) {
        $this->middleware('auth');
        $this->middleware('role_or_permission:print invoices|manage invoices');
    }

    public function show(int $invoice): View
    {
        $invoiceModel = $this->printService->findForPrint($invoice);
        $document = $this->printService->buildPrintData($invoiceModel);

        return view('invoices.print-batch', [
            'documents' => [$document],
        ]);
    }

    public function range(Request $request): View
    {
        try {
            $invoices = $this->printService->findRangeForPrint(
                (string) $request->query('from', ''),
                (string) $request->query('to', ''),
            );
        } catch (InvalidArgumentException $exception) {
            abort(422, $exception->getMessage());
        }

        if ($invoices->isEmpty()) {
            abort(404, 'No se encontraron facturas emitidas en ese rango.');
        }

        $documents = $invoices
            ->map(fn ($invoice) => $this->printService->buildPrintData($invoice))
            ->all();

        return view('invoices.print-batch', [
            'documents' => $documents,
        ]);
    }
}

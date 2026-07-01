<?php

namespace App\Http\Controllers;

use App\Services\InvoicePrintService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use InvalidArgumentException;

class InvoicePrintController extends Controller
{
    public function __construct(
        protected InvoicePrintService $printService,
    ) {
    }

    public function show(Request $request, int $invoice): View|Response
    {
        $invoiceModel = $this->printService->findForPrint($invoice);
        $documents = [$this->printService->buildPrintData($invoiceModel)];

        return $this->render($request, $documents);
    }

    public function range(Request $request): View|Response
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

        return $this->render($request, $documents);
    }

    /**
     * @param  array<int, array<string, mixed>>  $documents
     */
    protected function render(Request $request, array $documents): View|Response
    {
        $logoBase64 = $this->printService->logoBase64();
        $format = (string) $request->query('format', 'pdf');

        if ($format === 'html') {
            return view('invoices.print-batch', [
                'documents' => $documents,
                'logoBase64' => $logoBase64,
                'pdfUrl' => $request->fullUrlWithQuery(['format' => 'pdf']),
            ]);
        }

        $filename = $this->printService->pdfFilename($documents);

        return Pdf::loadView('invoices.pdf', [
            'documents' => $documents,
            'logoBase64' => $logoBase64,
        ])
            ->setPaper('a4')
            ->stream($filename);
    }
}

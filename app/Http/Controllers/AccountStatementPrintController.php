<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Services\AccountStatementPrintService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class AccountStatementPrintController extends Controller
{
    public function __construct(
        protected AccountStatementPrintService $printService,
    ) {
    }

    public function show(Request $request, Customer $customer): View|Response
    {
        $from = filled($request->query('from'))
            ? Carbon::parse((string) $request->query('from'))
            : null;
        $to = filled($request->query('to'))
            ? Carbon::parse((string) $request->query('to'))
            : null;
        $entryType = (string) $request->query('type', 'all');
        $excludeSettled = $request->boolean('exclude_settled');

        $document = $this->printService->buildPrintData(
            $customer,
            $from,
            $to,
            $entryType,
            $excludeSettled,
        );
        $logoBase64 = $this->printService->logoBase64();
        $format = (string) $request->query('format', 'pdf');

        if ($format === 'html') {
            return view('customers.statement-print', [
                'document' => $document,
                'logoBase64' => $logoBase64,
                'pdfUrl' => $request->fullUrlWithQuery(['format' => 'pdf']),
            ]);
        }

        return Pdf::loadView('customers.statement-print', [
            'document' => $document,
            'logoBase64' => $logoBase64,
            'pdfUrl' => null,
        ])
            ->setPaper('a4', 'portrait')
            ->setOption('defaultFont', 'DejaVu Sans')
            ->setOption('isHtml5ParserEnabled', true)
            ->stream($this->printService->pdfFilename($customer));
    }
}

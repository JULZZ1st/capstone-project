<?php

namespace App\Services\Reports;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class FinancialLedgerPdfExporter
{
    public function handle(array $report, string $filename): Response
    {
        return Pdf::loadView('reports.pdf.financial-ledger', ['report' => $report])
            ->setPaper('a4', 'portrait')
            ->download($filename);
    }
}

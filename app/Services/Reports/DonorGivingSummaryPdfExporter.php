<?php

namespace App\Services\Reports;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class DonorGivingSummaryPdfExporter
{
    public function handle(array $donorSummary, string $filename): Response
    {
        return Pdf::loadView('reports.pdf.donor-giving-summary', ['donorSummary' => $donorSummary])
            ->setPaper('a4', 'landscape')
            ->download($filename);
    }
}

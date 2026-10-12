<?php

namespace App\Services\Reports;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class InKindAuditPdfExporter
{
    public function handle(array $report, string $filename): Response
    {
        return Pdf::loadView('reports.pdf.in-kind-audit', ['report' => $report])
            ->setPaper('a4', 'landscape')
            ->download($filename);
    }
}

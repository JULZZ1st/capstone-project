<?php

namespace App\Services\Reports;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class InventoryReportPdfExporter
{
    public function handle(array $report, string $filename): Response
    {
        return Pdf::loadView('reports.pdf.inventory-summary', ['report' => $report])
            ->setPaper('a4', 'portrait')
            ->download($filename);
    }
}

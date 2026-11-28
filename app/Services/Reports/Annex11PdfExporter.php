<?php

namespace App\Services\Reports;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class Annex11PdfExporter
{
    public function handle(array $report, string $filename): Response
    {
        return Pdf::loadView('reports.pdf.annex-11', ['report' => $report])
            ->setPaper('a4', 'portrait')
            ->download($filename);
    }
}

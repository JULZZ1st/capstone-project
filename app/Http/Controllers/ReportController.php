<?php

namespace App\Http\Controllers;

use App\Http\Requests\Reports\ReportIndexRequest;
use App\Services\Reports\FinancialLedgerReportBuilder;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(ReportIndexRequest $request, FinancialLedgerReportBuilder $builder): View
    {
        $tab = $request->tab();

        return view('reports.index', [
            'tab' => $tab,
            'year' => $request->year(),
            'month' => $request->month(),
            'report' => $tab === 'financial-ledgers' ? $builder->build($request->year(), $request->month()) : null,
        ]);
    }
}

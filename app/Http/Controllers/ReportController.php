<?php

namespace App\Http\Controllers;

use App\Http\Requests\Reports\ReportIndexRequest;
use App\Services\Reports\DonorGivingSummaryPdfExporter;
use App\Services\Reports\DonorGivingSummaryReportBuilder;
use App\Services\Reports\FinancialLedgerCsvExporter;
use App\Services\Reports\FinancialLedgerPdfExporter;
use App\Services\Reports\FinancialLedgerReportBuilder;
use App\Services\Reports\InKindAuditCsvExporter;
use App\Services\Reports\InKindAuditPdfExporter;
use App\Services\Reports\InKindAuditReportBuilder;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(
        ReportIndexRequest $request,
        FinancialLedgerReportBuilder $builder,
        DonorGivingSummaryReportBuilder $donorSummaryBuilder,
        InKindAuditReportBuilder $inKindAuditBuilder
    ): View {
        $tab = $request->tab();

        return view('reports.index', [
            'tab' => $tab,
            'year' => $request->year(),
            'month' => $request->month(),
            'search' => $request->search(),
            'sort' => $request->sort(),
            'report' => $tab === 'financial-ledgers' ? $builder->build($request->year(), $request->month()) : null,
            'donorSummary' => $tab === 'philanthropy-roster'
                ? $donorSummaryBuilder->build([
                    'year' => $request->year(),
                    'month' => $request->month(),
                    'search' => $request->search(),
                    'sort' => $request->sort(),
                ])
                : null,
            'inKindAudit' => $tab === 'in-kind-audits'
                ? $inKindAuditBuilder->build($request->year(), $request->month())
                : null,
        ]);
    }

    public function financialLedgersCsv(
        ReportIndexRequest $request,
        FinancialLedgerReportBuilder $builder,
        FinancialLedgerCsvExporter $exporter
    ): StreamedResponse {
        $report = $builder->build($request->year(), $request->month());

        $filename = sprintf('financial-ledger-report-%04d-%02d.csv', $request->year(), $request->month());

        return $exporter->handle($report['ledger'], $filename);
    }

    public function financialLedgersPdf(
        ReportIndexRequest $request,
        FinancialLedgerReportBuilder $builder,
        FinancialLedgerPdfExporter $exporter
    ): Response {
        $report = $builder->build($request->year(), $request->month());

        $filename = sprintf('financial-ledger-report-%04d-%02d.pdf', $request->year(), $request->month());

        return $exporter->handle($report, $filename);
    }

    public function philanthropyRosterPdf(
        ReportIndexRequest $request,
        DonorGivingSummaryReportBuilder $builder,
        DonorGivingSummaryPdfExporter $exporter
    ): Response {
        $donorSummary = $builder->build([
            'year' => $request->year(),
            'month' => $request->month(),
            'search' => $request->search(),
            'sort' => $request->sort(),
        ]);

        $filename = sprintf('donor-giving-summary-%04d-%02d.pdf', $request->year(), $request->month());

        return $exporter->handle($donorSummary, $filename);
    }

    public function inKindAuditCsv(
        ReportIndexRequest $request,
        InKindAuditReportBuilder $builder,
        InKindAuditCsvExporter $exporter
    ): StreamedResponse {
        $report = $builder->build($request->year(), $request->month());
        $filename = sprintf('in-kind-inventory-audit-%04d-%02d.csv', $request->year(), $request->month());

        return $exporter->handle($report['movements'], $filename);
    }

    public function inKindAuditPdf(
        ReportIndexRequest $request,
        InKindAuditReportBuilder $builder,
        InKindAuditPdfExporter $exporter
    ): Response {
        $report = $builder->build($request->year(), $request->month());
        $filename = sprintf('in-kind-inventory-audit-%04d-%02d.pdf', $request->year(), $request->month());

        return $exporter->handle($report, $filename);
    }
}

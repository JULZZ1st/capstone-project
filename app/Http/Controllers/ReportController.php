<?php

namespace App\Http\Controllers;

use App\Http\Requests\Reports\ReportIndexRequest;
use App\Models\BankReconciliation;
use App\Services\Reports\AuditTrailReportBuilder;
use App\Services\Reports\DonorGivingSummaryPdfExporter;
use App\Services\Reports\DonorGivingSummaryReportBuilder;
use App\Services\Reports\FinancialLedgerCsvExporter;
use App\Services\Reports\FinancialLedgerPdfExporter;
use App\Services\Reports\FinancialLedgerReportBuilder;
use App\Services\Reports\InKindAuditCsvExporter;
use App\Services\Reports\InKindAuditPdfExporter;
use App\Services\Reports\InKindAuditReportBuilder;
use App\Services\Reports\InventoryReportBuilder;
use App\Services\Reports\InventoryReportPdfExporter;
use App\Services\Reports\ReconciliationReportBuilder;
use App\Services\Reports\ReportCsvExporter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(
        ReportIndexRequest $request,
        FinancialLedgerReportBuilder $builder,
        InventoryReportBuilder $inventoryBuilder,
        DonorGivingSummaryReportBuilder $donorSummaryBuilder,
        AuditTrailReportBuilder $auditBuilder,
        InKindAuditReportBuilder $inKindAuditBuilder
    ): View {
        $tab = $request->tab();

        return view('reports.index', [
            'tab' => $tab,
            'year' => $request->year(),
            'month' => $request->month(),
            'from' => $request->from(),
            'to' => $request->to(),
            'search' => $request->search(),
            'sort' => $request->sort(),
            'report' => in_array($tab, ['financial', 'financial-ledgers'], true) ? $builder->build($request->year(), $request->month()) : null,
            'inventoryReport' => $tab === 'inventory' ? $inventoryBuilder->build(['year' => $request->year(), 'month' => $request->month()]) : null,
            'auditReport' => $tab === 'audit' ? $auditBuilder->build($request->from(), $request->to()) : null,
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
            'reconciliationPeriods' => BankReconciliation::query()->orderByDesc('period_end')->get(),
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

    public function financialCsv(
        ReportIndexRequest $request,
        FinancialLedgerReportBuilder $builder,
        ReportCsvExporter $exporter
    ): StreamedResponse {
        $report = $builder->build($request->year(), $request->month());

        $rows = collect($report['ledger'])->map(fn (array $row) => [
            $row['entry_type'], $row['donor_name'], $row['campaign_name'] ?? 'General Fund',
            $row['payment_method_label'], number_format($row['amount'], 2, '.', ''),
            $row['received_at']->format('Y-m-d'), $row['status_label'],
        ]);

        return $exporter->handle($rows, ['Entry Type', 'Donor / Requester', 'Campaign / Purpose', 'Payment Method', 'Amount (PHP)', 'Date', 'Status'], sprintf('financial-report-%04d-%02d.csv', $request->year(), $request->month()));
    }

    public function financialPdf(
        ReportIndexRequest $request,
        FinancialLedgerReportBuilder $builder,
        FinancialLedgerPdfExporter $exporter
    ): Response {
        $report = $builder->build($request->year(), $request->month());

        $filename = sprintf('financial-report-%04d-%02d.pdf', $request->year(), $request->month());

        return $exporter->handle($report, $filename);
    }

    public function inventoryCsv(
        ReportIndexRequest $request,
        InventoryReportBuilder $builder,
        ReportCsvExporter $exporter
    ): StreamedResponse {
        $report = $builder->build(['year' => $request->year(), 'month' => $request->month()]);
        $rows = $report['items']->map(fn ($item) => [
            $item->name, $item->category, $item->unit, $item->current_quantity,
            $item->minimum_reserve_quantity, $item->stockStatus(), $item->expires_at?->toDateString(),
        ]);

        return $exporter->handle($rows, ['Item', 'Category', 'Unit', 'Current Stock', 'Minimum Reserve', 'Status', 'Expires'], sprintf('inventory-report-%04d-%02d.csv', $request->year(), $request->month()));
    }

    public function inventoryPdf(
        ReportIndexRequest $request,
        InventoryReportBuilder $builder,
        InventoryReportPdfExporter $exporter
    ): Response {
        $report = $builder->build(['year' => $request->year(), 'month' => $request->month()]);

        return $exporter->handle($report, sprintf('inventory-report-%04d-%02d.pdf', $request->year(), $request->month()));
    }

    public function auditCsv(
        ReportIndexRequest $request,
        AuditTrailReportBuilder $builder,
        ReportCsvExporter $exporter
    ): StreamedResponse {
        $report = $builder->build($request->from(), $request->to());
        $rows = collect($report['events'])->map(fn (array $event) => [
            $event['user'], $event['action'], $event['module'], $event['occurred_at']?->format('Y-m-d H:i:s'), $event['summary'],
        ]);

        return $exporter->handle($rows, ['User', 'Action', 'Module', 'Date/Time', 'Details'], 'audit-trail-report.csv');
    }

    public function auditPdf(
        ReportIndexRequest $request,
        AuditTrailReportBuilder $builder
    ): Response {
        $report = $builder->build($request->from(), $request->to());

        $filename = 'audit-trail-report.pdf';

        return Pdf::loadView('reports.pdf.audit-trail', compact('report'))->setPaper('a4', 'landscape')->download($filename);
    }

    public function inKindAuditCsv(
        ReportIndexRequest $request,
        InKindAuditReportBuilder $builder,
        InKindAuditCsvExporter $exporter
    ): StreamedResponse {
        $report = $builder->build($request->year(), $request->month());
        return $exporter->handle($report['movements'], sprintf('in-kind-inventory-audit-%04d-%02d.csv', $request->year(), $request->month()));
    }

    public function inKindAuditPdf(
        ReportIndexRequest $request,
        InKindAuditReportBuilder $builder,
        InKindAuditPdfExporter $exporter
    ): Response {
        $report = $builder->build($request->year(), $request->month());
        return $exporter->handle($report, sprintf('in-kind-inventory-audit-%04d-%02d.pdf', $request->year(), $request->month()));
    }

    public function reconciliationCsv(ReportIndexRequest $request, ReconciliationReportBuilder $builder, ReportCsvExporter $exporter): StreamedResponse
    {
        $periodId = $request->periodId();
        abort_unless($periodId, 422, 'A reconciliation period is required.');
        $report = $builder->build($periodId);
        $rows = $report['entries']->map(fn ($entry) => [$entry->transaction_date->format('Y-m-d'), $entry->description, $entry->transaction_type, $entry->amount, $entry->status]);
        return $exporter->handle($rows, ['Date', 'Description', 'Type', 'Amount', 'Status'], 'bank-reconciliation-'.$report['period']->id.'.csv');
    }

    public function reconciliationPdf(ReportIndexRequest $request, ReconciliationReportBuilder $builder): Response
    {
        $periodId = $request->periodId();
        abort_unless($periodId, 422, 'A reconciliation period is required.');
        $report = $builder->build($periodId);
        return Pdf::loadView('reports.pdf.reconciliation', compact('report'))->setPaper('a4', 'landscape')->download('bank-reconciliation-'.$report['period']->id.'.pdf');
    }

}

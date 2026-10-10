<?php

namespace App\Http\Controllers;

use App\Http\Requests\Reports\ReportIndexRequest;
use App\Services\Reports\AuditTrailReportBuilder;
use App\Services\Reports\FinancialLedgerPdfExporter;
use App\Services\Reports\FinancialLedgerReportBuilder;
use App\Services\Reports\InventoryReportBuilder;
use App\Services\Reports\InventoryReportPdfExporter;
use App\Services\Reports\ReportCsvExporter;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Barryvdh\DomPDF\Facade\Pdf;

class ReportController extends Controller
{
    public function index(
        ReportIndexRequest $request,
        FinancialLedgerReportBuilder $builder,
        InventoryReportBuilder $inventoryBuilder,
        AuditTrailReportBuilder $auditBuilder
    ): View {
        $tab = $request->tab();

        return view('reports.index', [
            'tab' => $tab,
            'year' => $request->year(),
            'month' => $request->month(),
            'from' => $request->from(),
            'to' => $request->to(),
            'report' => $tab === 'financial' ? $builder->build($request->year(), $request->month()) : null,
            'inventoryReport' => $tab === 'inventory' ? $inventoryBuilder->build(['year' => $request->year(), 'month' => $request->month()]) : null,
            'auditReport' => $tab === 'audit' ? $auditBuilder->build($request->from(), $request->to()) : null,
        ]);
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
}

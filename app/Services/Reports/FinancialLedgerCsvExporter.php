<?php

namespace App\Services\Reports;

use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinancialLedgerCsvExporter
{
    public function handle(Collection $ledgerRows, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($ledgerRows) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['Entry Type', 'Donor / Requester', 'Campaign / Purpose', 'Payment Method', 'Amount (PHP)', 'Received At', 'Status']);

            foreach ($ledgerRows as $row) {
                fputcsv($handle, [
                    $row['entry_type'] ?? 'Contribution',
                    $row['donor_name'],
                    $row['campaign_name'] ?? 'General Fund',
                    $row['payment_method_label'],
                    number_format($row['amount'], 2, '.', ''),
                    $row['received_at']->format('Y-m-d'),
                    $row['status_label'],
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}

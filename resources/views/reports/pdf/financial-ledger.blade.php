<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Financial Ledger Report</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1e293b; }
        h1 { font-size: 16px; margin-bottom: 2px; }
        .subtitle { color: #64748b; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #cbd5e1; padding: 6px 8px; text-align: left; }
        th { background-color: #f1f5f9; }
        .text-right { text-align: right; }
        .summary-table td { border: none; padding: 3px 8px; }
    </style>
</head>
<body>
    <h1>Barangay Financial Audit Summary</h1>
    <p class="subtitle">Month Cycle: {{ $report['period']->format('F Y') }}</p>

    <table class="summary-table">
        <tr>
            <td><strong>Total Reconciled Funds</strong></td>
            <td class="text-right">PHP {{ number_format($report['summary']['reconciled_total'], 2) }}</td>
        </tr>
        <tr>
            <td><strong>Total Pending Clearing</strong></td>
            <td class="text-right">PHP {{ number_format($report['summary']['pending_clearing_total'], 2) }}</td>
        </tr>
        <tr>
            <td><strong>Total Recorded Receipts</strong></td>
            <td class="text-right">PHP {{ number_format($report['summary']['recorded_total'], 2) }}</td>
        </tr>
        <tr>
            <td><strong>Total Disbursed (Approved Only)</strong></td>
            <td class="text-right">PHP {{ number_format($report['summary']['disbursement_total'], 2) }}</td>
        </tr>
        <tr>
            <td><strong>Net Total</strong></td>
            <td class="text-right">PHP {{ number_format($report['summary']['net_total'], 2) }}</td>
        </tr>
    </table>

    <h2 style="font-size:13px;">Settled Cash Receipts &amp; Disbursements Ledger</h2>
    <table>
        <thead>
            <tr>
                <th>Entry Type</th>
                <th>Donor / Requester</th>
                <th>Purpose</th>
                <th>Payment Method</th>
                <th class="text-right">Amount (PHP)</th>
                <th>Received At</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($report['ledger'] as $row)
                <tr>
                    <td>{{ $row['entry_type'] ?? 'Contribution' }}</td>
                    <td>{{ $row['donor_name'] }}</td>
                    <td>{{ $row['purpose'] }}</td>
                    <td>{{ $row['payment_method_label'] }}</td>
                    <td class="text-right">{{ number_format($row['amount'], 2) }}</td>
                    <td>{{ $row['received_at']->format('Y-m-d') }}</td>
                    <td>{{ $row['status_label'] }}</td>
                </tr>
            @empty
                <tr><td colspan="7">No cash receipts recorded for this period.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Donor Giving Summary</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1e293b; }
        h1 { font-size: 16px; margin-bottom: 2px; }
        .subtitle { color: #64748b; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #cbd5e1; padding: 6px 8px; text-align: left; }
        th { background-color: #f1f5f9; }
        .text-right { text-align: right; }
        small { color: #64748b; }
    </style>
</head>
<body>
    <h1>Donor Giving Summary</h1>
    <p class="subtitle">{{ $donorSummary['total_donors'] }} donor(s) with recorded giving</p>

    <table>
        <thead>
            <tr>
                <th>Donor</th>
                <th>Contact</th>
                <th class="text-right">Contributions</th>
                <th class="text-right">Cash</th>
                <th class="text-right">In-Kind (est.)</th>
                <th class="text-right">Combined</th>
                <th>First / Last Gift</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($donorSummary['rows'] as $row)
                <tr>
                    <td>{{ $row['donor_name'] }}</td>
                    <td>{{ $row['email'] ?: 'No email' }}<br><small>{{ $row['phone'] }}</small></td>
                    <td class="text-right">{{ number_format($row['total_donation_count']) }}</td>
                    <td class="text-right">{{ number_format($row['total_cash_donations'], 2) }}</td>
                    <td class="text-right">{{ number_format($row['total_in_kind_donations'], 2) }}</td>
                    <td class="text-right">{{ number_format($row['combined_donation_value'], 2) }}</td>
                    <td>{{ $row['first_donation_date'] ?? '-' }} &rarr; {{ $row['last_donation_date'] ?? '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="7">No donor giving activity found for this period.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>

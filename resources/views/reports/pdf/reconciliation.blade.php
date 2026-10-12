<!doctype html>
<html><body>
<h1>Bank Reconciliation Report</h1>
<p>Period: {{ $report['period']->period_start->format('Y-m-d') }} to {{ $report['period']->period_end->format('Y-m-d') }}</p>
<table><tr><th>Opening</th><th>Bank In</th><th>Bank Out</th><th>Raw Ending</th><th>Adjusted Bank</th><th>Ledger Ending</th><th>Difference</th></tr><tr><td>{{ number_format($report['period']->opening_balance, 2) }}</td><td>{{ number_format($report['period']->calculated_bank_cash_in, 2) }}</td><td>{{ number_format($report['period']->calculated_bank_cash_out, 2) }}</td><td>{{ number_format($report['period']->bank_ending_balance, 2) }}</td><td>{{ number_format($report['period']->adjusted_bank_balance, 2) }}</td><td>{{ number_format($report['period']->financial_ledger_ending_balance, 2) }}</td><td>{{ number_format($report['period']->reconciliation_difference, 2) }}</td></tr></table>
<h2>Bank Movements</h2><table><tr><th>Date</th><th>Description</th><th>Type</th><th>Amount</th><th>Status</th></tr>@foreach($report['entries'] as $entry)<tr><td>{{ $entry->transaction_date->format('Y-m-d') }}</td><td>{{ $entry->description }}</td><td>{{ $entry->transaction_type }}</td><td>{{ number_format($entry->amount, 2) }}</td><td>{{ $entry->status }}</td></tr>@endforeach</table>
<h2>Outstanding Items</h2>@forelse($report['outstanding'] as $item)<p>{{ $item->type }}: {{ number_format($item->amount, 2) }} — {{ $item->explanation }}</p>@empty<p>None</p>@endforelse
</body></html>

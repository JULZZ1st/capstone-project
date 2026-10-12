<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>In-Kind Inventory Audit</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9px; color: #1e293b; }
        h1 { font-size: 16px; margin-bottom: 2px; }
        .subtitle { color: #64748b; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
        th, td { border: 1px solid #cbd5e1; padding: 5px; text-align: left; }
        th { background-color: #f1f5f9; }
        .number { text-align: right; }
        .summary td { border: none; padding: 3px 8px; }
    </style>
</head>
<body>
    <h1>In-Kind Inventory Movement Audit</h1>
    <p class="subtitle">Period: {{ $report['period']->format('F Y') }} | Generated {{ now()->format('F d, Y') }}</p>

    <table class="summary">
        <tr>
            <td><strong>Movements</strong></td>
            <td class="number">{{ number_format($report['summary']['movement_count']) }}</td>
            <td><strong>Items Affected</strong></td>
            <td class="number">{{ number_format($report['summary']['item_count']) }}</td>
            <td><strong>Stock In</strong></td>
            <td class="number">{{ number_format($report['summary']['stock_in_quantity'], 2) }}</td>
            <td><strong>Stock Out</strong></td>
            <td class="number">{{ number_format($report['summary']['stock_out_quantity'], 2) }}</td>
        </tr>
    </table>

    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Item / Category</th>
                <th>Movement</th>
                <th class="number">Quantity</th>
                <th class="number">Before</th>
                <th class="number">After</th>
                <th>Reason / Reference</th>
                <th>Recorded By</th>
                <th>Remarks</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($report['movements'] as $movement)
                <tr>
                    <td>{{ $movement->created_at->format('Y-m-d H:i') }}</td>
                    <td>{{ $movement->inventoryItem?->name ?? 'Deleted inventory item' }}<br>{{ $movement->inventoryItem?->category }}{{ $movement->inventoryItem?->subcategory ? ' / '.$movement->inventoryItem->subcategory->label : '' }}</td>
                    <td>{{ str($movement->movement_type)->replace('_', ' ')->title() }}</td>
                    <td class="number">{{ number_format((float) $movement->quantity, 2) }} {{ $movement->inventoryItem?->unit }}</td>
                    <td class="number">{{ number_format((float) $movement->quantity_before, 2) }}</td>
                    <td class="number">{{ number_format((float) $movement->quantity_after, 2) }}</td>
                    <td>{{ str($movement->reason)->replace('_', ' ')->title() }}{{ $movement->reference_number ? ' / '.$movement->reference_number : '' }}</td>
                    <td>{{ $movement->creator?->name ?? 'Unknown user' }}</td>
                    <td>{{ $movement->remarks }}</td>
                </tr>
            @empty
                <tr><td colspan="9">No in-kind inventory movements were recorded for this period.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>

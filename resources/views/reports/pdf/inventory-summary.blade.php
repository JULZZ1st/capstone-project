<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Inventory Summary Report</title>
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
    <h1>Inventory Summary Report</h1>
    <p class="subtitle">Generated {{ now()->format('F d, Y') }}</p>

    <table class="summary-table">
        <tr>
            <td><strong>Total Items</strong></td>
            <td class="text-right">{{ number_format((int) $report['summary']->total_items) }}</td>
        </tr>
        <tr>
            <td><strong>In Stock</strong></td>
            <td class="text-right">{{ number_format((int) $report['summary']->in_stock) }}</td>
        </tr>
        <tr>
            <td><strong>Low Stock</strong></td>
            <td class="text-right">{{ number_format((int) $report['summary']->low_stock) }}</td>
        </tr>
        <tr>
            <td><strong>Out of Stock</strong></td>
            <td class="text-right">{{ number_format((int) $report['summary']->out_of_stock) }}</td>
        </tr>
    </table>

    <h2 style="font-size:13px;">Inventory Items</h2>
    <table>
        <thead>
            <tr>
                <th>Name</th>
                <th>Category / Subcategory</th>
                <th class="text-right">Current Qty</th>
                <th class="text-right">Min. Reserve</th>
                <th>Stock Status</th>
                <th>Storage Location</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($report['items'] as $item)
                <tr>
                    <td>{{ $item->name }}</td>
                    <td>{{ $item->category }}{{ $item->subcategory ? ' / '.$item->subcategory->label : '' }}</td>
                    <td class="text-right">{{ number_format((float) $item->current_quantity, 2) }} {{ $item->unit }}</td>
                    <td class="text-right">{{ number_format((float) $item->minimum_reserve_quantity, 2) }}</td>
                    <td>{{ ucfirst(str_replace('_', ' ', $item->stockStatus())) }}</td>
                    <td>{{ $item->storage_location }}</td>
                </tr>
            @empty
                <tr><td colspan="6">No inventory items match the current filters.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>

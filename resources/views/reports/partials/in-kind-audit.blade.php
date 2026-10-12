<section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
    <div class="flex flex-col gap-2 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Report Status: Inventory Movement Audit</p>
            <h2 class="text-sm font-bold uppercase text-slate-950">In-Kind Stock Activity</h2>
        </div>
        <p class="text-xs text-slate-500">Period: {{ $report['period']->format('F Y') }}</p>
    </div>

    <div class="grid gap-3 border-b border-slate-100 p-5 sm:grid-cols-2 xl:grid-cols-5">
        <div class="rounded-lg bg-slate-50 p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Movements</p>
            <p class="mt-1 text-xl font-bold text-slate-950">{{ number_format($report['summary']['movement_count']) }}</p>
        </div>
        <div class="rounded-lg bg-slate-50 p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Items Affected</p>
            <p class="mt-1 text-xl font-bold text-slate-950">{{ number_format($report['summary']['item_count']) }}</p>
        </div>
        <div class="rounded-lg bg-emerald-50 p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-emerald-800">Stock In</p>
            <p class="mt-1 text-xl font-bold text-emerald-950">{{ number_format($report['summary']['stock_in_quantity'], 2) }}</p>
        </div>
        <div class="rounded-lg bg-amber-50 p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-amber-800">Stock Out</p>
            <p class="mt-1 text-xl font-bold text-amber-950">{{ number_format($report['summary']['stock_out_quantity'], 2) }}</p>
        </div>
        <div class="rounded-lg bg-blue-50 p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-blue-800">Adjustments</p>
            <p class="mt-1 text-xl font-bold text-blue-950">{{ number_format($report['summary']['adjustment_quantity'], 2) }}</p>
        </div>
    </div>

    <div class="p-5">
        <div class="mb-4">
            <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Stock Movement History</h3>
            <p class="mt-1 text-xs text-slate-500">Each row records the quantity change and the staff member who recorded it.</p>
        </div>

        @if ($report['movements']->isEmpty())
            <x-dashboard.empty-state message="No in-kind inventory movements were recorded for this period." />
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="bg-slate-100 text-left">
                            <th class="whitespace-nowrap p-3 font-semibold text-slate-600">Date</th>
                            <th class="p-3 font-semibold text-slate-600">Item</th>
                            <th class="p-3 font-semibold text-slate-600">Movement</th>
                            <th class="whitespace-nowrap p-3 text-right font-semibold text-slate-600">Quantity</th>
                            <th class="whitespace-nowrap p-3 text-right font-semibold text-slate-600">Before → After</th>
                            <th class="p-3 font-semibold text-slate-600">Reason / Reference</th>
                            <th class="p-3 font-semibold text-slate-600">Recorded By</th>
                            <th class="p-3 font-semibold text-slate-600">Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($report['movements'] as $movement)
                            <tr class="border-t border-slate-100 align-top">
                                <td class="whitespace-nowrap p-3 text-slate-600">{{ $movement->created_at->format('M d, Y H:i') }}</td>
                                <td class="p-3">
                                    <p class="font-medium text-slate-900">{{ $movement->inventoryItem?->name ?? 'Deleted inventory item' }}</p>
                                    @if ($movement->inventoryItem)
                                        <p class="mt-0.5 text-xs text-slate-500">{{ str($movement->inventoryItem->category)->replace('_', ' ')->title() }}{{ $movement->inventoryItem->subcategory ? ' / '.$movement->inventoryItem->subcategory->label : '' }}</p>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap p-3 text-slate-700">{{ str($movement->movement_type)->replace('_', ' ')->title() }}</td>
                                <td class="whitespace-nowrap p-3 text-right font-medium text-slate-900">{{ number_format((float) $movement->quantity, 2) }} {{ $movement->inventoryItem?->unit }}</td>
                                <td class="whitespace-nowrap p-3 text-right text-slate-600">{{ number_format((float) $movement->quantity_before, 2) }} → {{ number_format((float) $movement->quantity_after, 2) }}</td>
                                <td class="p-3 text-slate-700">
                                    {{ str($movement->reason)->replace('_', ' ')->title() }}
                                    @if ($movement->reference_number)
                                        <p class="mt-0.5 text-xs text-slate-500">Ref: {{ $movement->reference_number }}</p>
                                    @endif
                                </td>
                                <td class="p-3 text-slate-600">{{ $movement->creator?->name ?? 'Unknown user' }}</td>
                                <td class="max-w-xs whitespace-pre-line p-3 text-slate-600">{{ $movement->remarks ?: '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</section>

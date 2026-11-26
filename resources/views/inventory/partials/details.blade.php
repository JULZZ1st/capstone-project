    <div class="space-y-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <a href="{{ route('inventory.index') }}" class="text-sm font-semibold text-blue-700 hover:text-blue-800">Back to inventory</a>
                <h1 class="mt-2 text-2xl font-semibold text-slate-950">{{ $inventoryItem->name }}</h1>
                <p class="mt-1 text-sm text-slate-600">{{ $inventoryItem->description ?: 'No description provided.' }}</p>
            </div>
            <x-inventory.status-badge :status="$inventoryItem->stockStatus()" />
        </div>

        <x-inventory.stock-summary :item="$inventoryItem" />

        <div class="min-w-0">
            <x-data-table caption="Recent stock movements" title="Recent Stock Movements" min-width="52rem">
                <x-slot:head>
                    <x-data-table.heading>Date</x-data-table.heading>
                    <x-data-table.heading>Type</x-data-table.heading>
                    <x-data-table.heading align="right">Before</x-data-table.heading>
                    <x-data-table.heading align="right">Quantity</x-data-table.heading>
                    <x-data-table.heading align="right">After</x-data-table.heading>
                    <x-data-table.heading>Reason</x-data-table.heading>
                    <x-data-table.heading>Source</x-data-table.heading>
                    <x-data-table.heading>Logged by</x-data-table.heading>
                </x-slot:head>
                @forelse ($inventoryItem->stockMovements as $movement)
                    <tr>
                        <td class="px-4 py-3 text-slate-600">{{ $movement->created_at?->format('M d, Y g:i A') }}</td>
                        <td class="px-4 py-3 font-medium text-slate-900">{{ config('inventory.movement_types.'.$movement->movement_type, $movement->movement_type) }}</td>
                        <td class="data-table__number">{{ number_format((float) $movement->quantity_before, 2) }}</td>
                        <td class="data-table__number">{{ number_format((float) $movement->quantity, 2) }}</td>
                        <td class="data-table__number">{{ number_format((float) $movement->quantity_after, 2) }}</td>
                        <td class="px-4 py-3">{{ config('inventory.adjustment_reasons.'.$movement->reason, $movement->reason) }}</td>
                        <td class="px-4 py-3">
                            @if ($movement->contributionItem?->contribution)
                                <a href="{{ route('donations.show', $movement->contributionItem->contribution) }}" class="font-medium text-blue-700 hover:text-blue-800">
                                    {{ $movement->contributionItem->contribution->receipt_number }}
                                </a>
                                <span class="block text-xs text-slate-500">{{ $movement->contributionItem->contribution->donor?->full_name }}</span>
                            @else
                                <span class="text-slate-400">Manual / imported</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">{{ $movement->creator?->name ?? 'System' }}</td>
                    </tr>
                @empty
                    <x-data-table.empty :columns="8" title="No stock movements have been recorded for this item." />
                @endforelse
            </x-data-table>
        </div>
    </div>

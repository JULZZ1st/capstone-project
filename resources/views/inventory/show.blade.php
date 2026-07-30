@extends('layouts.app')

@section('title', $inventoryItem->name.' | In-Kind Inventory')
@section('page-title', 'In-Kind Inventory')

@section('content')
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

        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
            <div class="border-b border-slate-200 px-4 py-3">
                <h2 class="text-sm font-semibold text-slate-950">Recent Stock Movements</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Date</th>
                            <th class="px-4 py-3">Type</th>
                            <th class="px-4 py-3 text-right">Before</th>
                            <th class="px-4 py-3 text-right">Quantity</th>
                            <th class="px-4 py-3 text-right">After</th>
                            <th class="px-4 py-3">Reason</th>
                            <th class="px-4 py-3">Logged by</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @forelse ($inventoryItem->stockMovements as $movement)
                            <tr>
                                <td class="px-4 py-3 text-slate-600">{{ $movement->created_at?->format('M d, Y g:i A') }}</td>
                                <td class="px-4 py-3 font-medium text-slate-900">{{ config('inventory.movement_types.'.$movement->movement_type, $movement->movement_type) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format((float) $movement->quantity_before, 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format((float) $movement->quantity, 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format((float) $movement->quantity_after, 2) }}</td>
                                <td class="px-4 py-3">{{ config('inventory.adjustment_reasons.'.$movement->reason, $movement->reason) }}</td>
                                <td class="px-4 py-3">{{ $movement->creator?->name ?? 'System' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-8">
                                    <x-dashboard.empty-state message="No stock movements have been recorded for this item." />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

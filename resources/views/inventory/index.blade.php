@extends('layouts.app')

@section('title', 'In-Kind Inventory')
@section('page-title', 'In-Kind Inventory')

@section('content')
    @if ($errors->any())
        <x-alert type="error" class="mb-6">Please review the highlighted fields and try again.</x-alert>
    @endif

    <div class="space-y-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-slate-950">In-Kind Inventory</h1>
                <p class="mt-1 text-sm text-slate-600">Track donated goods, reserve levels, and stock movement.</p>
            </div>

            @can('create', App\Models\InventoryItem::class)
                <x-button type="button" class="sm:w-auto" data-dialog-open="create-inventory-item-dialog">
                    Add New Inventory Item
                </x-button>
            @endcan
        </div>

        <x-inventory.alert-panel :alerts="$alerts" />

        <div class="rounded-lg border border-slate-200 bg-white p-4">
            <div class="space-y-4">
                <x-inventory.filter-tabs :filters="$filters" />
                <x-inventory.search-bar :filters="$filters" />
            </div>
        </div>

        @if ($items->count())
            <x-inventory.table :items="$items" />
            {{ $items->links() }}
        @else
            <div class="rounded-lg border border-slate-200 bg-white p-8">
                <x-dashboard.empty-state :message="request()->hasAny(['search', 'category', 'status'])
                    ? 'No inventory items match the current filters.'
                    : 'No inventory items have been recorded yet.'" />

                <div class="mt-4 flex justify-center gap-3">
                    @if (request()->hasAny(['search', 'category', 'status']))
                        <a href="{{ route('inventory.index') }}" class="text-sm font-semibold text-blue-700 hover:text-blue-800">Clear filters</a>
                    @endif
                    @can('create', App\Models\InventoryItem::class)
                        <button type="button" class="text-sm font-semibold text-blue-700 hover:text-blue-800" data-dialog-open="create-inventory-item-dialog">
                            Add New Inventory Item
                        </button>
                    @endcan
                </div>
            </div>
        @endif
    </div>

    @can('create', App\Models\InventoryItem::class)
        <x-inventory.create-modal />
    @endcan
@endsection

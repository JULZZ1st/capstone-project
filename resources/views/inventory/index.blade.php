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
                <h1 class="text-2xl font-bold tracking-tight text-slate-950">In-Kind Inventory</h1>
                <p class="mt-1 text-sm text-slate-500">Track donated goods, reserve levels, and stock movement.</p>
            </div>

            <div class="flex flex-col gap-2 sm:flex-row">
                @can('create', App\Models\InventoryCategory::class)
                    <a href="{{ route('inventory.categories.index') }}" class="inline-flex items-center justify-center rounded-md border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                        Manage Categories &amp; Units
                    </a>
                @endcan
                @can('create', App\Models\InventoryItem::class)
                    <x-button type="button" class="w-auto shadow-sm" data-dialog-open="create-inventory-item-dialog">
                        <x-icon name="plus" class="-ml-0.5 mr-1.5 h-4 w-4" />
                        Add New Inventory Item
                    </x-button>
                @endcan
            </div>
        </div>

        <x-inventory.alert-panel :alerts="$alerts" />

        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="space-y-4">
                <x-inventory.filter-tabs :filters="$filters" />
                <div class="border-t border-slate-100 pt-4">
                    <x-inventory.search-bar :filters="$filters" />
                </div>
            </div>
        </div>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-dashboard.summary-card
                label="Total Items"
                :value="number_format((int) $stockSummary->total_items)"
                description="All inventory items."
                icon="archive"
                tone="neutral"
            />
            <x-dashboard.summary-card
                label="In Stock"
                :value="number_format((int) $stockSummary->in_stock)"
                description="Items available."
                icon="check-circle"
                tone="positive"
            />
            <x-dashboard.summary-card
                label="Low Stock"
                :value="number_format((int) $stockSummary->low_stock)"
                description="Below reorder level."
                icon="alert-triangle"
                tone="warning"
            />
            <x-dashboard.summary-card
                label="Out of Stock"
                :value="number_format((int) $stockSummary->out_of_stock)"
                description="No items available."
                icon="x-circle"
                tone="danger"
            />
        </section>

        @if ($items->count())
            <x-inventory.table :items="$items" />
        @else
            <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-5 py-4">
                    <h2 class="text-base font-semibold text-slate-950">Inventory Items</h2>
                </div>
                <div class="p-8">
                    <x-dashboard.empty-state
                        icon="cube"
                        :message="request()->hasAny(['search', 'category', 'status'])
                            ? 'No inventory items match the current filters.'
                            : 'No inventory items have been recorded yet.'"
                    />
                    <p class="mt-1 text-center text-sm text-slate-400">
                        @if (request()->hasAny(['search', 'category', 'status']))
                            Try adjusting your search or filters.
                        @else
                            Start by adding a new inventory item.
                        @endif
                    </p>

                    <div class="mt-4 flex flex-wrap items-center justify-center gap-3">
                        @if (request()->hasAny(['search', 'category', 'status']))
                            <a href="{{ route('inventory.index') }}" class="text-sm font-semibold text-blue-700 hover:text-blue-800">Clear filters</a>
                        @endif
                        @can('create', App\Models\InventoryItem::class)
                            <x-button type="button" class="w-auto" data-dialog-open="create-inventory-item-dialog">
                                <x-icon name="plus" class="-ml-0.5 mr-1.5 h-4 w-4" />
                                Add New Inventory Item
                            </x-button>
                        @endcan
                    </div>
                </div>
            </div>
        @endif
    </div>

    @can('create', App\Models\InventoryItem::class)
        <x-inventory.create-modal :categories="$categories" :units="$units" />
    @endcan
@endsection

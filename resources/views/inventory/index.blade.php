@extends('layouts.app')

@section('title', 'In-Kind Inventory')
@section('page-title', 'In-Kind Inventory')
@section('page-subtitle', 'Track donated goods, reserve levels, and stock movement.')

@section('content')
    @if ($errors->any())
        <x-alert type="error" class="mb-6">Please review the highlighted fields and try again.</x-alert>
    @endif

    <div class="space-y-6">
        <form method="GET" action="{{ route('inventory.index') }}" id="inventory-filter-form" class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="min-w-0 lg:max-w-sm lg:flex-1">
                <div class="relative">
                    <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                    <input
                        id="search"
                        name="search"
                        value="{{ $filters['search'] ?? '' }}"
                        placeholder="Search by item name, category, batch, location, or notes"
                        autocomplete="off"
                        aria-label="Search inventory"
                        class="ui-control block h-10 w-full py-2 pl-9 pr-3"
                        data-inventory-autosearch
                    >
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <select id="inventory-filter-category" name="category" aria-label="Category" class="ui-control ui-select--compact h-10 w-44 py-2" data-inventory-autosubmit>
                    <option value="">All Categories</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->key }}" @selected(($filters['category'] ?? null) === $category->key)>{{ $category->label }}</option>
                    @endforeach
                </select>

                <select id="inventory-filter-status" name="status" aria-label="Status" class="ui-control ui-select--compact h-10 w-40 py-2" data-inventory-autosubmit>
                    <option value="">All statuses</option>
                    @foreach (config('inventory.item_statuses') as $key => $status)
                        <option value="{{ $key }}" @selected(($filters['status'] ?? null) === $key)>{{ $status['label'] }}</option>
                    @endforeach
                </select>

                @can('create', App\Models\InventoryCategory::class)
                    <a href="{{ route('inventory.categories.index') }}" class="ui-button ui-button--secondary w-auto">
                        Manage Categories &amp; Units
                    </a>
                @endcan

                @can('create', App\Models\InventoryItem::class)
                    <a href="{{ route('inventory.create') }}" class="ui-button ui-button--primary w-auto gap-2">
                        <x-icon name="plus" class="h-4 w-4" />
                        Add New Inventory Item
                    </a>
                @endcan
            </div>
        </form>

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

                    @if (request()->hasAny(['search', 'category', 'status']))
                        <div class="mt-4 flex flex-wrap items-center justify-center gap-3">
                            <a href="{{ route('inventory.index') }}" class="text-sm font-semibold text-blue-700 hover:text-blue-800">Clear filters</a>
                        </div>
                    @endif
                </div>
            </div>
        @endif
    </div>
@endsection

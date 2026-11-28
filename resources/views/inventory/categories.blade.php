@extends('layouts.app')

@section('title', 'Manage Item Categories')
@section('page-title', 'Manage Item Categories')
@section('page-subtitle', 'Add, rename, or retire the categories, subcategories, and units used across in-kind inventory.')

@section('content')
    @if ($errors->any())
        <x-alert type="error" class="mb-6">Please review the highlighted fields and try again.</x-alert>
    @endif

    <div class="space-y-4" data-inventory-reference-tabs>
        <div>
            <a href="{{ route('inventory.index') }}" class="inline-flex h-10 items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50">
                <x-icon name="arrow-left" class="h-4 w-4" />
                Back to In-Kind Inventory
            </a>
        </div>

        <div class="flex overflow-x-auto border-b border-slate-200" role="tablist" aria-label="Inventory reference data">
            @foreach (['categories' => 'Categories', 'subcategories' => 'Subcategories', 'units' => 'Units'] as $tab => $label)
                <button type="button" role="tab" aria-selected="{{ $tab === 'categories' ? 'true' : 'false' }}" aria-controls="{{ $tab }}-panel" data-reference-tab="{{ $tab }}" class="shrink-0 border-b-2 px-4 py-2.5 text-sm font-semibold transition {{ $tab === 'categories' ? 'border-blue-600 text-blue-700' : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-800' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>

        <x-inventory.reference-table
            title="Categories"
            :items="$categories"
            :store-route="route('inventory.categories.store')"
            update-route-name="inventory.categories.update"
            delete-route-name="inventory.categories.destroy"
            deactivate-route-name="inventory.categories.deactivate"
            reactivate-route-name="inventory.categories.reactivate"
            dialog-id="category"
            panel-id="categories-panel"
        />
        <x-inventory.subcategory-table :categories="$categories" panel-id="subcategories-panel" />
        <x-inventory.reference-table
            title="Units"
            :items="$units"
            :store-route="route('inventory.units.store')"
            update-route-name="inventory.units.update"
            delete-route-name="inventory.units.destroy"
            deactivate-route-name="inventory.units.deactivate"
            reactivate-route-name="inventory.units.reactivate"
            dialog-id="unit"
            panel-id="units-panel"
        />
    </div>
@endsection

@extends('layouts.app')

@section('title', 'Manage Item Categories')
@section('page-title', 'Manage Item Categories')
@section('page-subtitle', 'Add, rename, or retire the categories, subcategories, and units used across in-kind inventory.')

@section('content')
    @if ($errors->any())
        <x-alert type="error" class="mb-6">Please review the highlighted fields and try again.</x-alert>
    @endif

    <div class="space-y-6">
        <div>
            <a href="{{ route('inventory.index') }}" class="text-sm font-semibold text-blue-700 hover:text-blue-800">Back to inventory</a>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <x-inventory.reference-table
                title="Categories"
                :items="$categories"
                :store-route="route('inventory.categories.store')"
                update-route-name="inventory.categories.update"
                delete-route-name="inventory.categories.destroy"
                dialog-id="category"
            />
            <x-inventory.reference-table
                title="Units"
                :items="$units"
                :store-route="route('inventory.units.store')"
                update-route-name="inventory.units.update"
                delete-route-name="inventory.units.destroy"
                dialog-id="unit"
            />
        </div>

        <x-inventory.subcategory-table :categories="$categories" />
    </div>
@endsection

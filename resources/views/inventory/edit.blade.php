@extends('layouts.app')

@section('title', 'Edit Inventory Item')
@section('page-title', 'Edit Inventory Item')
@section('page-subtitle', 'Update item information, estimated value, and reserve levels.')

@section('content')
    @if ($errors->any())
        <x-alert type="error" class="mb-6">Please review the highlighted fields and try again.</x-alert>
    @endif

    <div class="space-y-6">
        <div>
            <a href="{{ route('inventory.show', $inventoryItem) }}" class="text-sm font-semibold text-blue-700 hover:text-blue-800">&larr; Back to Item Details</a>
        </div>

        <form method="POST" action="{{ route('inventory.update', $inventoryItem) }}" class="ui-card overflow-hidden">
            @csrf
            @method('PATCH')

            <div class="px-5 py-5 sm:px-6">
                <x-inventory.item-form :item="$inventoryItem" :categories="$categories" :units="$units" />
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 bg-slate-50/70 px-5 py-3.5 sm:px-6">
                <a href="{{ route('inventory.show', $inventoryItem) }}" class="ui-button ui-button--secondary w-auto px-4">Cancel</a>
                <x-button class="h-10 w-auto rounded-lg px-5">Save Changes</x-button>
            </div>
        </form>
    </div>
@endsection

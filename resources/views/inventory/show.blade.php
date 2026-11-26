@extends('layouts.app')

@section('title', $inventoryItem->name.' | In-Kind Inventory')
@section('page-title', 'In-Kind Inventory')

@section('content')
    <div class="space-y-6" data-inventory-live>
        <x-inventory.live-updates />
        <div data-inventory-results>
            @include('inventory.partials.details')
        </div>
    </div>
@endsection

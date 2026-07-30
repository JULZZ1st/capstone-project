@extends('layouts.app')

@section('title', 'In-Kind Inventory and Financial Ledger | '.config('dashboard.barangay_name'))
@section('page-title', 'In-Kind Inventory and Financial Ledger')

@section('content')
    <div class="grid gap-6 lg:grid-cols-2">
        <x-dashboard.panel title="Secretary: In-Kind Inventory" subtitle="Record and review in-kind donation inventory.">
            <form action="{{ route('workflows.inventory.store') }}" method="POST" class="space-y-3">
                @csrf
                <input name="name" placeholder="Item Name" class="w-full rounded-md border-slate-300 px-3 py-2 text-sm" required>
                <input name="category" placeholder="Category" class="w-full rounded-md border-slate-300 px-3 py-2 text-sm" required>
                <input name="quantity" type="number" min="1" placeholder="Quantity" class="w-full rounded-md border-slate-300 px-3 py-2 text-sm" required>
                <textarea name="description" placeholder="Description" class="w-full rounded-md border-slate-300 px-3 py-2 text-sm"></textarea>
                <input name="received_at" type="date" class="w-full rounded-md border-slate-300 px-3 py-2 text-sm">
                <input name="status" placeholder="Status" value="{{ config('dashboard.inventory_statuses.received') }}" class="w-full rounded-md border-slate-300 px-3 py-2 text-sm">
                <x-button class="w-auto">Save Inventory</x-button>
            </form>

            <div class="mt-6">
                <h3 class="mb-2 text-sm font-semibold text-slate-700">Inventory List</h3>
                @if($inventoryItems->isEmpty())
                    <x-dashboard.empty-state message="No inventory items recorded yet." />
                @else
                    <ul class="space-y-2">
                        @foreach($inventoryItems as $item)
                            <li class="rounded-md border border-slate-200 p-3 text-sm">
                                {{ $item->name }} - Qty: {{ number_format($item->quantity) }} ({{ $item->category }})
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </x-dashboard.panel>

        <x-dashboard.panel title="Treasurer: Cash Transactions" subtitle="Record and review cash ledger entries.">
            <form action="{{ route('cash.store') }}" method="POST" class="space-y-3">
                @csrf
                <input name="donor_name" placeholder="Donor Name" class="w-full rounded-md border-slate-300 px-3 py-2 text-sm" required>
                <input name="amount" type="number" step="0.01" placeholder="Amount" class="w-full rounded-md border-slate-300 px-3 py-2 text-sm" required>
                <input name="purpose" placeholder="Purpose" class="w-full rounded-md border-slate-300 px-3 py-2 text-sm" required>
                <input name="received_at" type="date" class="w-full rounded-md border-slate-300 px-3 py-2 text-sm">
                <input name="status" placeholder="Status" value="{{ config('dashboard.cash_statuses.verified') }}" class="w-full rounded-md border-slate-300 px-3 py-2 text-sm">
                <textarea name="notes" placeholder="Notes" class="w-full rounded-md border-slate-300 px-3 py-2 text-sm"></textarea>
                <x-button class="w-auto bg-green-700 hover:bg-green-800">Save Cash Transaction</x-button>
            </form>

            <div class="mt-6">
                <h3 class="mb-2 text-sm font-semibold text-slate-700">Cash Transactions</h3>
                @if($cashTransactions->isEmpty())
                    <x-dashboard.empty-state message="No cash transactions recorded yet." />
                @else
                    <ul class="space-y-2">
                        @foreach($cashTransactions as $transaction)
                            <li class="rounded-md border border-slate-200 p-3 text-sm">
                                {{ $transaction->donor_name }} - PHP {{ number_format((float) $transaction->amount, 2) }} for {{ $transaction->purpose }}
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </x-dashboard.panel>
    </div>
@endsection

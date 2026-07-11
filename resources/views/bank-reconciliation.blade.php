@extends('layouts.app')

@section('title', 'Bank Reconciliation | '.config('dashboard.barangay_name'))
@section('page-title', 'Bank Reconciliation')

@section('content')
    <div class="space-y-6">
        <x-dashboard.panel title="Add Reconciliation" subtitle="Reconcile cash transactions with bank balances.">
            <form action="{{ route('bank-reconciliation.store') }}" method="POST" class="grid gap-4 md:grid-cols-2">
                @csrf
                <input name="date" type="date" class="rounded-md border-slate-300 px-3 py-2 text-sm" required>
                <input name="opening_balance" type="number" step="0.01" placeholder="Opening Balance" class="rounded-md border-slate-300 px-3 py-2 text-sm" required>
                <input name="cash_in" type="number" step="0.01" placeholder="Cash In" class="rounded-md border-slate-300 px-3 py-2 text-sm" required>
                <input name="cash_out" type="number" step="0.01" placeholder="Cash Out" class="rounded-md border-slate-300 px-3 py-2 text-sm" required>
                <textarea name="notes" placeholder="Notes" class="rounded-md border-slate-300 px-3 py-2 text-sm md:col-span-2"></textarea>
                <x-button class="w-auto bg-purple-700 hover:bg-purple-800 md:col-span-2">Save Reconciliation</x-button>
            </form>
        </x-dashboard.panel>

        <x-dashboard.panel title="Reconciliation Records">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                    <tr class="bg-slate-100">
                        <th class="p-2 text-left">Date</th>
                        <th class="p-2 text-left">Opening</th>
                        <th class="p-2 text-left">Cash In</th>
                        <th class="p-2 text-left">Cash Out</th>
                        <th class="p-2 text-left">Closing</th>
                        <th class="p-2 text-left">Notes</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($reconciliations as $entry)
                        <tr class="border-t">
                            <td class="p-2">{{ $entry->date }}</td>
                            <td class="p-2">PHP {{ number_format((float) $entry->opening_balance, 2) }}</td>
                            <td class="p-2">PHP {{ number_format((float) $entry->cash_in, 2) }}</td>
                            <td class="p-2">PHP {{ number_format((float) $entry->cash_out, 2) }}</td>
                            <td class="p-2">PHP {{ number_format((float) $entry->closing_balance, 2) }}</td>
                            <td class="p-2">{{ $entry->notes }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="p-4 text-center text-slate-500">No reconciliation records yet.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </x-dashboard.panel>
    </div>
@endsection

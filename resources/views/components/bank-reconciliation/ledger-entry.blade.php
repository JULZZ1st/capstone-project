@props(['entry', 'suggestions', 'pendingReceipts'])

@php($hasSuggestions = $suggestions->isNotEmpty())

<div class="border-t border-slate-100 px-5 py-4 first:border-t-0">
    <div class="flex items-start justify-between gap-4">
        <div class="min-w-0">
            <p class="text-xs text-slate-400">
                {{ $entry->transaction_date->format('Y-m-d') }} &middot; STATEMENT ID {{ $entry->statement_id }}
            </p>
            <p class="mt-1 truncate text-sm font-semibold text-slate-950">{{ $entry->description }}</p>
        </div>
        <p class="flex-none text-base font-bold text-slate-950">&#8369;{{ number_format((float) $entry->amount, 0) }}</p>
    </div>

    <div class="mt-3 flex items-center justify-between gap-4">
        <p class="truncate text-xs text-slate-400">REF: {{ $entry->reference_number ?? '—' }}</p>

        @if ($hasSuggestions && ! auth()->user()?->can('reconcile', $entry))
            <span class="text-xs font-semibold text-emerald-700">
                {{ $suggestions->count() }} {{ \Illuminate\Support\Str::plural('suggestion', $suggestions->count()) }} found:
                {{ $suggestions->first()['contribution']->donor?->full_name }}
                ({{ $suggestions->first()['contribution']->receipt_number }})
            </span>
        @endif

        @can('reconcile', $entry)
            <button
                type="button"
                data-dialog-open="match-entry-{{ $entry->id }}"
                @class([
                    'inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-semibold whitespace-nowrap',
                    'bg-emerald-100 text-emerald-800 hover:bg-emerald-200' => $hasSuggestions,
                    'text-slate-400 hover:text-slate-600' => ! $hasSuggestions,
                ])
            >
            @if ($hasSuggestions)
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-3.5 w-3.5"><path d="M12.232 4.232a2.5 2.5 0 013.536 3.536l-1.225 1.224a.75.75 0 001.061 1.06l1.224-1.224a4 4 0 00-5.656-5.656l-3 3a4 4 0 00.225 5.865.75.75 0 00.977-1.138 2.5 2.5 0 01-.140-3.667l3-3z" /><path d="M11.603 7.963a.75.75 0 00-.977 1.138 2.5 2.5 0 01.14 3.667l-3 3a2.5 2.5 0 01-3.536-3.536l1.225-1.224a.75.75 0 00-1.061-1.06l-1.224 1.224a4 4 0 105.656 5.656l3-3a4 4 0 00-.223-5.865z" /></svg>
                {{ $suggestions->count() }} {{ \Illuminate\Support\Str::plural('suggestion', $suggestions->count()) }} found
            @else
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-3.5 w-3.5"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm.75-13a.75.75 0 00-1.5 0v5c0 .199.079.39.22.53l3.5 3.5a.75.75 0 101.06-1.06l-3.28-3.28V5z" clip-rule="evenodd" /></svg>
                No direct ref match
            @endif
            </button>
        @endcan
    </div>
</div>

@can('reconcile', $entry)
    <x-bank-reconciliation.match-modal :entry="$entry" :suggestions="$suggestions" :pending-receipts="$pendingReceipts" />
@endcan

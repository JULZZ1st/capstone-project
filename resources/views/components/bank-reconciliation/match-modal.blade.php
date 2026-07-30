@props(['entry', 'suggestions', 'pendingReceipts'])

@php($dialogId = 'match-entry-'.$entry->id)

<dialog id="{{ $dialogId }}" class="w-[min(640px,calc(100vw-2rem))] rounded-lg p-0 shadow-xl backdrop:bg-slate-950/50">
    <form method="POST" action="{{ route('bank-reconciliation.reconcile') }}">
        @csrf
        <input type="hidden" name="bank_ledger_entry_id" value="{{ $entry->id }}">

        <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
            <div>
                <h2 class="text-lg font-semibold text-slate-950">Smart Match Verification</h2>
                <p class="text-sm text-slate-500">{{ $entry->description }} &middot; &#8369;{{ number_format((float) $entry->amount, 2) }}</p>
            </div>
            <button type="button" class="rounded-md px-2 py-1 text-2xl leading-none text-slate-500 hover:bg-slate-100" data-dialog-close="{{ $dialogId }}">&times;</button>
        </div>

        <div class="max-h-[60vh] space-y-3 overflow-y-auto px-5 py-5">
            @if ($suggestions->isNotEmpty())
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Suggested Matches</p>

                @foreach ($suggestions as $suggestion)
                    <label class="flex cursor-pointer items-start gap-3 rounded-md border border-slate-200 p-3 text-sm hover:border-emerald-400 hover:bg-emerald-50/50">
                        <input type="radio" name="contribution_id" value="{{ $suggestion['contribution']->id }}" class="mt-1" @checked($loop->first) required>
                        <span class="min-w-0 flex-1">
                            <span class="flex items-center justify-between gap-2">
                                <span class="font-semibold text-slate-950">{{ $suggestion['contribution']->donor?->full_name ?? 'Unknown Donor' }}</span>
                                <span class="font-semibold text-slate-950">&#8369;{{ number_format((float) $suggestion['contribution']->amount, 2) }}</span>
                            </span>
                            <span class="mt-1 block text-xs text-slate-500">{{ implode(' &middot; ', $suggestion['reasons']) }}</span>
                        </span>
                    </label>
                @endforeach
            @else
                <p class="rounded-md bg-slate-50 px-3 py-2 text-sm text-slate-500">
                    No pending receipt shares a reference number, amount, or channel with this entry. Choose one manually below.
                </p>

                <select name="contribution_id" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500" required>
                    <option value="">Select a pending receipt</option>
                    @foreach ($pendingReceipts as $receipt)
                        <option value="{{ $receipt->id }}">
                            {{ $receipt->donor?->full_name ?? 'Unknown Donor' }} &mdash; &#8369;{{ number_format((float) $receipt->amount, 2) }}
                        </option>
                    @endforeach
                </select>
            @endif
        </div>

        <div class="flex flex-col-reverse gap-3 border-t border-slate-200 bg-slate-50 px-5 py-4 sm:flex-row sm:justify-end">
            <button type="button" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-white" data-dialog-close="{{ $dialogId }}">Cancel</button>
            <x-button class="sm:w-auto bg-emerald-700 hover:bg-emerald-800">Confirm Reconciliation</x-button>
        </div>
    </form>
</dialog>

@props(['contribution'])

<div class="border-t border-slate-100 px-5 py-4 first:border-t-0">
    <div class="flex items-start justify-between gap-4">
        <div class="min-w-0">
            <p class="truncate text-sm font-semibold text-slate-950">{{ $contribution->donor?->full_name ?? 'Unknown Donor' }}</p>
            <p class="mt-1 truncate text-xs text-slate-500">
                Receipt {{ $contribution->receipt_number }} &middot; Received {{ $contribution->received_at?->format('Y-m-d') }}
            </p>
        </div>
        <p class="flex-none text-base font-bold text-emerald-700">&#8369;{{ number_format((float) $contribution->amount, 0) }}</p>
    </div>

    <div class="mt-3 flex items-center justify-between gap-4">
        <p class="truncate text-xs text-slate-400">
            {{ config("donations.payment_methods.{$contribution->payment_method}", ucfirst((string) $contribution->payment_method)) }}
            &middot; External ref: {{ $contribution->reference_number ?? '—' }}
        </p>

        {{-- eligibleForReconciliation() already excludes contributions with a ledger match, so this list is always pending. --}}
        <x-bank-reconciliation.badge status="pending" variant="receipt" />
    </div>
</div>

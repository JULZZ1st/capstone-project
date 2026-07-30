@props(['unreconciledFeedCount', 'pendingReceiptsCount'])

<div class="rounded-lg bg-emerald-950 px-5 py-5 text-white sm:px-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div class="flex gap-3">
            <span class="mt-0.5 flex h-6 w-6 flex-none items-center justify-center rounded bg-emerald-400/20 text-emerald-300">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4">
                    <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" />
                </svg>
            </span>
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-emerald-200">Treasurer Audit Protocols</p>
                <p class="mt-1 max-w-2xl text-sm leading-relaxed text-emerald-50/80">
                    Match bank entries on the left with corresponding logged donations on the right. Once verified, the donation transitions to
                    <span class="font-semibold text-white">Reconciled</span> status and becomes permanently auditable.
                </p>
            </div>
        </div>

        <div class="flex gap-6">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-wide text-emerald-300">Unreconciled Feed</p>
                <p class="mt-1 text-lg font-bold text-white">{{ $unreconciledFeedCount }} {{ \Illuminate\Support\Str::plural('entry', $unreconciledFeedCount) }}</p>
            </div>
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-wide text-emerald-300">Pending Ledger Receipts</p>
                <p class="mt-1 text-lg font-bold text-white">{{ $pendingReceiptsCount }} {{ \Illuminate\Support\Str::plural('item', $pendingReceiptsCount) }}</p>
            </div>
        </div>
    </div>
</div>

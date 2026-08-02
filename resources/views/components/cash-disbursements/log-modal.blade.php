<dialog id="log-disbursement-dialog" class="w-[min(760px,calc(100vw-2rem))] rounded-lg p-0 shadow-xl backdrop:bg-slate-950/50" @if($errors->any()) open @endif>
    <form method="POST" action="{{ route('cash-disbursements.store') }}" class="max-h-[90vh] overflow-y-auto bg-white">
        @csrf

        <div class="sticky top-0 z-10 flex items-center justify-between border-b border-slate-200 bg-white px-5 py-4">
            <div>
                <h2 class="text-lg font-semibold text-slate-950">Record Cash Disbursement</h2>
                <p class="text-sm text-slate-500">Log an outgoing cash payment such as aid payout or an operational expense.</p>
            </div>
            <button type="button" class="rounded-md px-2 py-1 text-2xl leading-none text-slate-500 hover:bg-slate-100" data-dialog-close="log-disbursement-dialog">&times;</button>
        </div>

        <div class="space-y-5 px-5 py-5">
            <x-cash-disbursements.form />
        </div>

        <div class="flex flex-col-reverse gap-3 border-t border-slate-200 bg-slate-50 px-5 py-4 sm:flex-row sm:justify-end">
            <button type="button" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-white" data-dialog-close="log-disbursement-dialog">Cancel</button>
            <x-button class="sm:w-auto">Save Disbursement</x-button>
        </div>
    </form>
</dialog>

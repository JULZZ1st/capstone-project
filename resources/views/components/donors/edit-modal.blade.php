@props(['donor', 'open' => false, 'startStep' => 0])

<dialog id="edit-donor-modal-{{ $donor->id }}" class="m-auto w-[calc(100%-2rem)] max-w-2xl overflow-hidden rounded-xl p-0 shadow-xl backdrop:bg-slate-950/50" @if($open) open @endif>
    <form method="POST" action="{{ route('donors.update', $donor) }}" class="flex max-h-[86vh] flex-col bg-white" data-donor-wizard-form novalidate>
        @csrf
        @method('PATCH')
        <input type="hidden" name="_modal" value="edit-{{ $donor->id }}">

        <div class="flex shrink-0 items-start justify-between gap-4 border-b border-slate-200 px-4 py-3 sm:px-6">
            <div>
                <h2 class="text-lg font-semibold text-slate-950">Edit Donor Profile</h2>
                <p class="mt-1 text-sm text-slate-500">{{ $donor->full_name }}</p>
            </div>
            <button type="button" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-slate-500 transition hover:bg-slate-100 hover:text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500" data-dialog-close="edit-donor-modal-{{ $donor->id }}" aria-label="Close modal">
                <x-icon name="x-circle" class="h-5 w-5" />
            </button>
        </div>

        <div class="flex min-h-0 flex-1 flex-col">
            <x-donors.form :donor="$donor" mode="edit" :start-step="$startStep" />
        </div>

        <div class="flex shrink-0 items-center justify-between gap-3 border-t border-slate-200 bg-slate-50/90 px-4 py-3 sm:px-6">
            <button type="button" class="h-9 rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-45" data-wizard-back disabled>Back</button>
            <div class="flex items-center gap-2">
                <button type="button" class="h-9 rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-white" data-dialog-close="edit-donor-modal-{{ $donor->id }}">Cancel</button>
                <button type="button" class="h-9 rounded-lg bg-blue-700 px-4 text-sm font-semibold text-white hover:bg-blue-800" data-wizard-next>Next</button>
                <x-button class="h-9 w-auto rounded-lg px-5" data-wizard-submit hidden>Update Profile</x-button>
            </div>
        </div>
        <div data-ui-toast class="pointer-events-none fixed bottom-4 right-4 z-[200] hidden w-[min(24rem,calc(100vw-2rem))] rounded-lg border border-slate-200 bg-white p-4 text-slate-950 shadow-lg shadow-slate-950/20" role="status" aria-live="polite" aria-atomic="true">
            <div class="flex items-start gap-3">
                <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-amber-100 text-xs font-bold text-amber-700">!</span>
                <div class="min-w-0">
                    <p class="text-sm font-semibold" data-ui-toast-title>Required field missing</p>
                    <p class="mt-1 text-sm text-slate-600" data-ui-toast-description>Please complete the highlighted required field.</p>
                </div>
            </div>
        </div>
    </form>
</dialog>

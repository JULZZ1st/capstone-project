@props(['open' => false, 'startStep' => 0, 'dialogId' => 'create-donor-modal'])

@php($modalKey = $dialogId === 'create-donor-modal' ? 'create' : $dialogId)

<dialog id="{{ $dialogId }}" class="m-auto w-[calc(100%-2rem)] max-w-2xl overflow-hidden rounded-xl p-0 shadow-2xl backdrop:bg-slate-950/55" @if($open) open @endif>
    <form method="POST" action="{{ route('donors.store') }}" class="flex max-h-[86vh] flex-col bg-white" data-donor-create-form data-donor-wizard-form novalidate>
        @csrf
        <input type="hidden" name="_modal" value="{{ $modalKey }}">
        @if (session('duplicate_donor_detected'))
            <input type="hidden" name="_confirm_duplicate" value="1">
        @endif

        <div class="flex shrink-0 items-start justify-between gap-4 border-b border-slate-200 px-4 py-3 sm:px-6">
            <div class="flex min-w-0 items-start gap-3">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-blue-700 ring-1 ring-inset ring-blue-100">
                    <x-icon name="users" class="h-5 w-5" />
                </div>
                <div class="min-w-0">
                    <h2 class="text-lg font-semibold text-slate-950">Add New Donor Profile</h2>
                    <p class="mt-0.5 text-sm text-slate-500">Create a donor profile for future donation records.</p>
                </div>
            </div>
            <button type="button" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-slate-500 transition hover:bg-slate-100 hover:text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500" data-dialog-close="{{ $dialogId }}" aria-label="Close modal">
                <x-icon name="x-circle" class="h-5 w-5" />
            </button>
        </div>

        <div class="flex min-h-0 flex-1 flex-col">
            @if (session('warning'))
                <x-alert type="warning" class="mx-4 mt-3 sm:mx-6">{{ session('warning') }}</x-alert>
            @endif
            <x-donors.form mode="create" :start-step="$startStep" :form-key="$modalKey" :id-prefix="$dialogId === 'create-donor-modal' ? '' : $dialogId.'_'" />
        </div>

        <div class="flex shrink-0 items-center justify-between gap-3 border-t border-slate-200 bg-slate-50/90 px-4 py-3 sm:px-6">
            <button type="button" class="h-9 rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-45" data-wizard-back disabled>Back</button>
            <div class="flex items-center gap-2">
                <button type="button" class="h-9 rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50" data-dialog-close="{{ $dialogId }}">Cancel</button>
                <button type="button" class="h-9 rounded-lg bg-blue-700 px-4 text-sm font-semibold text-white hover:bg-blue-800" data-wizard-next>Next</button>
                <x-button class="h-9 w-auto rounded-lg px-5" data-wizard-submit hidden>Save Donor Profile</x-button>
            </div>
        </div>
    </form>
</dialog>

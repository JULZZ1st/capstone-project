@props(['donor'])

@php($dialogId = 'remove-donor-modal-'.$donor->id)

<dialog
    id="{{ $dialogId }}"
    class="m-auto w-[calc(100%-2rem)] max-w-md rounded-xl p-0 shadow-2xl backdrop:bg-slate-950/55"
    aria-labelledby="{{ $dialogId }}-title"
    aria-describedby="{{ $dialogId }}-description"
>
    <div class="bg-white">
        <div class="flex items-start gap-3 border-b border-slate-200 px-5 py-4">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-red-50 text-red-700 ring-1 ring-inset ring-red-100">
                <x-icon name="alert-triangle" class="h-5 w-5" />
            </span>
            <div class="min-w-0 flex-1">
                <h2 id="{{ $dialogId }}-title" class="text-base font-semibold text-slate-950">Confirm donor removal</h2>
                <p class="mt-1 text-sm font-medium text-slate-700">{{ $donor->full_name }}</p>
            </div>
            <button
                type="button"
                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-slate-500 transition hover:bg-slate-100 hover:text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500"
                data-dialog-close="{{ $dialogId }}"
                aria-label="Close confirmation"
            >
                <x-icon name="x-circle" class="h-5 w-5" />
            </button>
        </div>

        <p id="{{ $dialogId }}-description" class="px-5 py-4 text-sm leading-6 text-slate-600">
            @if ($donor->contributions_count > 0)
                This donor has donation history, so the profile will be deactivated and the history preserved.
            @else
                This will permanently delete the donor profile. This action cannot be undone.
            @endif
        </p>

        <div class="flex justify-end gap-2 border-t border-slate-200 bg-slate-50/90 px-5 py-3">
            <button
                type="button"
                class="h-9 rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                data-dialog-close="{{ $dialogId }}"
            >
                Cancel
            </button>
            <form method="POST" action="{{ route('donors.destroy', $donor) }}">
                @csrf
                @method('DELETE')
                <button type="submit" class="h-9 rounded-lg bg-red-700 px-4 text-sm font-semibold text-white transition hover:bg-red-800 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2">
                    @if ($donor->contributions_count > 0)
                        Confirm Deactivation
                    @else
                        Confirm Delete
                    @endif
                </button>
            </form>
        </div>
    </div>
</dialog>
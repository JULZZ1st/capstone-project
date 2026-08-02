@props(['dialogId', 'action', 'method', 'item', 'title'])

<dialog id="{{ $dialogId }}" class="w-[min(480px,calc(100vw-2rem))] rounded-lg p-0 shadow-xl backdrop:bg-slate-950/50">
    <form method="POST" action="{{ $action }}" class="bg-white">
        @csrf
        @if (strtoupper($method) !== 'POST')
            @method($method)
        @endif

        <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
            <h2 class="text-base font-semibold text-slate-950">{{ $title }}</h2>
            <button type="button" class="rounded-md px-2 py-1 text-sm text-slate-500 hover:bg-slate-100" data-dialog-close="{{ $dialogId }}">Close</button>
        </div>

        <div class="space-y-4 px-5 py-4">
            <x-form.input name="key" label="Key" :value="$item?->key" required />
            <x-form.input name="label" label="Label" :value="$item?->label" required />

            <div>
                <input type="hidden" name="is_active" value="0">
                <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" name="is_active" value="1" class="rounded border-slate-300" @checked(old('is_active', $item?->is_active ?? true))>
                    Active
                </label>
            </div>
        </div>

        <div class="flex flex-col-reverse gap-3 border-t border-slate-200 bg-slate-50 px-5 py-4 sm:flex-row sm:justify-end">
            <button type="button" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-white" data-dialog-close="{{ $dialogId }}">Cancel</button>
            <x-button class="sm:w-auto">Save</x-button>
        </div>
    </form>
</dialog>

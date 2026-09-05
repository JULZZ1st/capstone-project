@props(['dialogId', 'action', 'method', 'subcategory', 'categories', 'title'])

<dialog id="{{ $dialogId }}" class="w-[min(520px,calc(100vw-2rem))] rounded-lg p-0 shadow-xl backdrop:bg-slate-950/50">
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
            <div>
                <x-form.label for="{{ $dialogId }}_inventory_category_id">Parent Category</x-form.label>
                <select id="{{ $dialogId }}_inventory_category_id" name="inventory_category_id" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                    <option value="">Select category</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected((string) old('inventory_category_id', $subcategory?->inventory_category_id) === (string) $category->id)>{{ $category->label }}</option>
                    @endforeach
                </select>
                <x-form.error name="inventory_category_id" />
            </div>

            <x-form.input name="key" label="Key" :value="$subcategory?->key" required />
            <x-form.input name="label" label="Label" :value="$subcategory?->label" required />

            <div>
                <input type="hidden" name="is_active" value="0">
                <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" name="is_active" value="1" class="rounded border-slate-300" @checked(old('is_active', $subcategory?->is_active ?? true))>
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

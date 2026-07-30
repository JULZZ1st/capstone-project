@php($open = old('_modal') === 'create')

<dialog id="create-inventory-item-dialog" class="w-[min(860px,calc(100vw-2rem))] rounded-lg p-0 shadow-xl backdrop:bg-slate-950/50" @if($open) open @endif>
    <form method="POST" action="{{ route('inventory.store') }}" class="max-h-[90vh] overflow-y-auto bg-white">
        @csrf
        <input type="hidden" name="_modal" value="create">

        <div class="sticky top-0 z-10 flex items-start justify-between border-b border-slate-200 bg-white px-6 py-4">
            <div>
                <h2 class="text-lg font-semibold text-slate-950">Add New Inventory Item</h2>
                <p class="mt-1 text-sm text-slate-500">Record donated goods and reserve levels.</p>
            </div>
            <button type="button" class="rounded-md px-2 py-1 text-sm text-slate-500 hover:bg-slate-100" data-dialog-close="create-inventory-item-dialog">Close</button>
        </div>

        <div class="space-y-4 px-6 py-5">
            <x-inventory.item-form />
        </div>

        <div class="flex flex-col-reverse gap-3 border-t border-slate-200 bg-slate-50 px-6 py-4 sm:flex-row sm:justify-end">
            <button type="button" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-white" data-dialog-close="create-inventory-item-dialog">Cancel</button>
            <x-button class="sm:w-auto">Save Inventory Item</x-button>
        </div>
    </form>
</dialog>

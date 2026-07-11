@props(['open' => false])

<dialog id="create-donor-modal" class="w-full max-w-2xl rounded-lg p-0 shadow-xl backdrop:bg-slate-950/50" @if($open) open @endif>
    <form method="POST" action="{{ route('donors.store') }}" class="max-h-[90vh] overflow-y-auto bg-white">
        @csrf
        <input type="hidden" name="_modal" value="create">

        <div class="flex items-start justify-between border-b border-slate-200 px-6 py-4">
            <div>
                <h2 class="text-lg font-semibold text-slate-950">Add New Donor Profile</h2>
                <p class="mt-1 text-sm text-slate-500">Create a donor profile for future donation records.</p>
            </div>
            <button type="button" class="rounded-md px-2 py-1 text-sm text-slate-500 hover:bg-slate-100" data-dialog-close="create-donor-modal">Close</button>
        </div>

        <div class="space-y-4 px-6 py-5">
            <x-donors.form />
        </div>

        <div class="flex justify-end gap-3 border-t border-slate-200 px-6 py-4">
            <button type="button" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50" data-dialog-close="create-donor-modal">Cancel</button>
            <x-button class="w-auto">Save Donor Profile</x-button>
        </div>
    </form>
</dialog>

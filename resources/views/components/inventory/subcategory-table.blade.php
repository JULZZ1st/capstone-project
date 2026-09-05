@props(['categories'])

<div class="rounded-lg border border-slate-200 bg-white p-4">
    <div class="mb-3 flex items-center justify-between">
        <h2 class="text-base font-semibold text-slate-950">Subcategories</h2>
        <x-button type="button" class="w-auto" data-dialog-open="subcategory-create">Add Subcategory</x-button>
    </div>

    @if ($categories->flatMap->subcategories->isEmpty())
        <x-dashboard.empty-state message="No subcategories recorded yet." />
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                    <tr>
                        <th class="px-3 py-2">Category</th>
                        <th class="px-3 py-2">Key</th>
                        <th class="px-3 py-2">Label</th>
                        <th class="px-3 py-2">Status</th>
                        <th class="px-3 py-2 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @foreach ($categories as $category)
                        @foreach ($category->subcategories as $subcategory)
                            <tr>
                                <td class="px-3 py-2 text-slate-900">{{ $category->label }}</td>
                                <td class="px-3 py-2 font-mono text-xs text-slate-600">{{ $subcategory->key }}</td>
                                <td class="px-3 py-2 text-slate-900">{{ $subcategory->label }}</td>
                                <td class="px-3 py-2">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset {{ $subcategory->is_active ? 'bg-emerald-100 text-emerald-800 ring-emerald-200' : 'bg-slate-100 text-slate-600 ring-slate-200' }}">
                                        {{ $subcategory->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 text-right">
                                    <div class="flex justify-end gap-2">
                                        <button type="button" class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50" data-dialog-open="subcategory-edit-{{ $subcategory->id }}">Edit</button>
                                        <form method="POST" action="{{ route('inventory.subcategories.destroy', $subcategory) }}" onsubmit="return confirm('Delete this entry? This cannot be undone.');">
                                            @csrf
                                            @method('DELETE')
                                            <button class="rounded-md border border-red-300 px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-50">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <x-inventory.subcategory-form-modal
                                dialog-id="subcategory-edit-{{ $subcategory->id }}"
                                :action="route('inventory.subcategories.update', $subcategory)"
                                method="PATCH"
                                :subcategory="$subcategory"
                                :categories="$categories"
                                title="Edit Subcategory"
                            />
                        @endforeach
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

<x-inventory.subcategory-form-modal
    dialog-id="subcategory-create"
    :action="route('inventory.subcategories.store')"
    method="POST"
    :subcategory="null"
    :categories="$categories"
    title="Add Subcategory"
/>

@props(['categories'])

<div class="rounded-lg border border-slate-200 bg-white p-4">
    <div class="mb-3 flex items-center justify-between">
        <h2 class="text-base font-semibold text-slate-950">Subcategories</h2>
        <x-button type="button" class="w-auto" data-dialog-open="subcategory-create">Add Subcategory</x-button>
    </div>

    @if ($categories->flatMap->subcategories->isEmpty())
        <x-dashboard.empty-state message="No subcategories recorded yet." />
    @else
        <div class="min-w-0">
            <x-data-table caption="Inventory subcategories" min-width="44rem">
<x-slot:head>
                        <x-data-table.heading>Category</x-data-table.heading>
                        <x-data-table.heading>Key</x-data-table.heading>
                        <x-data-table.heading>Label</x-data-table.heading>
                        <x-data-table.heading>Status</x-data-table.heading>
                        <x-data-table.heading align="right">Actions</x-data-table.heading>
                    </x-slot:head>

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

                        @endforeach
                    @endforeach

</x-data-table>
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

@foreach ($categories as $category)
    @foreach ($category->subcategories as $subcategory)
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

@props(['categories', 'panelId'])

<section id="{{ $panelId }}" role="tabpanel" data-reference-panel="subcategories" hidden class="min-w-0 rounded-xl border border-slate-200 bg-white p-3 shadow-sm sm:p-4">
    <div class="mb-3 flex justify-end">
        <x-button type="button" class="w-auto px-3 py-2" data-dialog-open="subcategory-create">
            <x-icon name="plus" class="h-4 w-4" />
            Add Subcategory
        </x-button>
    </div>

    @if ($categories->flatMap->subcategories->isEmpty())
        <x-dashboard.empty-state message="No subcategories recorded yet." />
    @else
        <div class="min-w-0">
            <x-data-table caption="Inventory subcategories" min-width="32rem" max-height="none" class="compact-reference-table shadow-none">
<x-slot:head>
                        <x-data-table.heading>Name</x-data-table.heading>
                        <x-data-table.heading>Status</x-data-table.heading>
                        <x-data-table.heading align="right">Actions</x-data-table.heading>
                    </x-slot:head>

                    @foreach ($categories as $category)
                        @foreach ($category->subcategories as $subcategory)
                            @php($inUse = $subcategory->inventory_item_count > 0 || $subcategory->donation_item_count > 0)
                            <tr>
                                <td>
                                    <span class="block font-medium text-slate-900">{{ $subcategory->label }}</span>
                                    <span class="block text-xs text-slate-500">{{ $category->label }}</span>
                                    <span class="block text-xs text-slate-500">
                                        {{ $subcategory->inventory_item_count }} inventory {{ Illuminate\Support\Str::plural('item', $subcategory->inventory_item_count) }} · {{ $subcategory->donation_item_count }} donation {{ Illuminate\Support\Str::plural('record', $subcategory->donation_item_count) }}
                                    </span>
                                </td>
                                <td class="px-3 py-2">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset {{ $subcategory->is_active ? 'bg-emerald-100 text-emerald-800 ring-emerald-200' : 'bg-slate-100 text-slate-600 ring-slate-200' }}">
                                        {{ $subcategory->is_active ? 'Active' : 'Retired' }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 text-right">
                                    <div class="flex justify-end gap-2">
                                        <button type="button" class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50" data-dialog-open="subcategory-edit-{{ $subcategory->id }}">Edit</button>

                                        @if ($subcategory->is_active && $inUse)
                                            <button type="button" class="rounded-md border border-amber-300 px-3 py-1.5 text-xs font-semibold text-amber-700 hover:bg-amber-50" data-dialog-open="subcategory-deactivate-{{ $subcategory->id }}">Deactivate</button>
                                        @endif

                                        @if (! $subcategory->is_active)
                                            <button type="button" class="rounded-md border border-emerald-300 px-3 py-1.5 text-xs font-semibold text-emerald-700 hover:bg-emerald-50" data-dialog-open="subcategory-reactivate-{{ $subcategory->id }}">Reactivate</button>
                                        @endif

                                        @if (! $inUse)
                                            <button type="button" class="rounded-md border border-red-300 px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-50" data-dialog-open="subcategory-delete-{{ $subcategory->id }}">Delete</button>
                                        @endif
                                    </div>
                                </td>
                            </tr>

                        @endforeach
                    @endforeach

</x-data-table>
        </div>
    @endif
</section>

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
        @php($inUse = $subcategory->inventory_item_count > 0 || $subcategory->donation_item_count > 0)

        <x-inventory.subcategory-form-modal
            dialog-id="subcategory-edit-{{ $subcategory->id }}"
            :action="route('inventory.subcategories.update', $subcategory)"
            method="PATCH"
            :subcategory="$subcategory"
            :categories="$categories"
            title="Edit Subcategory"
        />

        @if ($subcategory->is_active && $inUse)
            <x-inventory.confirm-action-dialog
                :dialog-id="'subcategory-deactivate-'.$subcategory->id"
                :action="route('inventory.subcategories.deactivate', $subcategory)"
                method="PATCH"
                title="Deactivate Subcategory"
                confirm-label="Deactivate"
            >
                <p>Deactivating <strong>{{ $subcategory->label }}</strong> removes it from selection on new entries. It stays visible on {{ $subcategory->inventory_item_count }} inventory {{ Illuminate\Support\Str::plural('item', $subcategory->inventory_item_count) }} and {{ $subcategory->donation_item_count }} donation {{ Illuminate\Support\Str::plural('record', $subcategory->donation_item_count) }}. You can reactivate it later.</p>
            </x-inventory.confirm-action-dialog>
        @endif

        @if (! $subcategory->is_active)
            <x-inventory.confirm-action-dialog
                :dialog-id="'subcategory-reactivate-'.$subcategory->id"
                :action="route('inventory.subcategories.reactivate', $subcategory)"
                method="PATCH"
                title="Reactivate Subcategory"
                confirm-label="Reactivate"
            >
                <p>Reactivating <strong>{{ $subcategory->label }}</strong> makes it selectable again for new entries.</p>
            </x-inventory.confirm-action-dialog>
        @endif

        @if (! $inUse)
            <x-inventory.confirm-action-dialog
                :dialog-id="'subcategory-delete-'.$subcategory->id"
                :action="route('inventory.subcategories.destroy', $subcategory)"
                method="DELETE"
                title="Delete Subcategory"
                confirm-label="Delete"
                confirm-variant="danger"
            >
                <p>Deleting <strong>{{ $subcategory->label }}</strong> permanently removes it. It is not used by any inventory items or donation records, so this cannot be undone.</p>
            </x-inventory.confirm-action-dialog>
        @endif
    @endforeach
@endforeach

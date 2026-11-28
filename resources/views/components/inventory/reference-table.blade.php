@props(['title', 'items', 'storeRoute', 'updateRouteName', 'deleteRouteName', 'deactivateRouteName', 'reactivateRouteName', 'dialogId', 'panelId'])

<section id="{{ $panelId }}" role="tabpanel" data-reference-panel="{{ Illuminate\Support\Str::lower($title) }}" @hidden($title !== 'Categories') class="min-w-0 rounded-xl border border-slate-200 bg-white p-3 shadow-sm sm:p-4">
    <div class="mb-3 flex justify-end">
        <x-button type="button" class="w-auto px-3 py-2" data-dialog-open="{{ $dialogId }}-create">
            <x-icon name="plus" class="h-4 w-4" />
            Add {{ Illuminate\Support\Str::singular($title) }}
        </x-button>
    </div>

    @if ($items->isEmpty())
        <x-dashboard.empty-state :message="'No '.Illuminate\Support\Str::lower($title).' recorded yet.'" />
    @else
        <div class="min-w-0">
            <x-data-table caption="{{ $title }}" min-width="32rem" max-height="none" class="compact-reference-table shadow-none">
<x-slot:head>
                        <x-data-table.heading>Name</x-data-table.heading>
                        <x-data-table.heading>Status</x-data-table.heading>
                        <x-data-table.heading align="right">Actions</x-data-table.heading>
                    </x-slot:head>

                    @foreach ($items as $item)
                        @php($inUse = $item->inventory_item_count > 0 || ($item->subcategory_count ?? 0) > 0)
                        <tr>
                            <td class="font-medium text-slate-900">
                                {{ $item->label }}
                                <span class="block text-xs font-normal text-slate-500">
                                    @if (isset($item->subcategory_count))
                                        {{ $item->subcategory_count }} {{ Illuminate\Support\Str::plural('subcategory', $item->subcategory_count) }} ·
                                    @endif
                                    {{ $item->inventory_item_count }} inventory {{ Illuminate\Support\Str::plural('item', $item->inventory_item_count) }}
                                </span>
                            </td>
                            <td class="px-3 py-2">
                                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset {{ $item->is_active ? 'bg-emerald-100 text-emerald-800 ring-emerald-200' : 'bg-slate-100 text-slate-600 ring-slate-200' }}">
                                    {{ $item->is_active ? 'Active' : 'Retired' }}
                                </span>
                            </td>
                            <td class="px-3 py-2 text-right">
                                <div class="flex justify-end gap-2">
                                    <button type="button" class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50" data-dialog-open="{{ $dialogId }}-edit-{{ $item->id }}">Edit</button>

                                    @if ($item->is_active && $inUse)
                                        <button type="button" class="rounded-md border border-amber-300 px-3 py-1.5 text-xs font-semibold text-amber-700 hover:bg-amber-50" data-dialog-open="{{ $dialogId }}-deactivate-{{ $item->id }}">Deactivate</button>
                                    @endif

                                    @if (! $item->is_active)
                                        <button type="button" class="rounded-md border border-emerald-300 px-3 py-1.5 text-xs font-semibold text-emerald-700 hover:bg-emerald-50" data-dialog-open="{{ $dialogId }}-reactivate-{{ $item->id }}">Reactivate</button>
                                    @endif

                                    @if (! $inUse)
                                        <button type="button" class="rounded-md border border-red-300 px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-50" data-dialog-open="{{ $dialogId }}-delete-{{ $item->id }}">Delete</button>
                                    @endif
                                </div>
                            </td>
                        </tr>

                    @endforeach

</x-data-table>
        </div>
    @endif
</section>

<x-inventory.reference-form-modal
    :dialog-id="$dialogId.'-create'"
    :action="$storeRoute"
    method="POST"
    :item="null"
    :title="'Add '.Illuminate\Support\Str::singular($title)"
/>

@foreach ($items as $item)
    @php($inUse = $item->inventory_item_count > 0 || ($item->subcategory_count ?? 0) > 0)

    <x-inventory.reference-form-modal
        :dialog-id="$dialogId.'-edit-'.$item->id"
        :action="route($updateRouteName, $item)"
        method="PATCH"
        :item="$item"
        :title="'Edit '.Illuminate\Support\Str::singular($title)"
    />

    @if ($item->is_active && $inUse)
        <x-inventory.confirm-action-dialog
            :dialog-id="$dialogId.'-deactivate-'.$item->id"
            :action="route($deactivateRouteName, $item)"
            method="PATCH"
            :title="'Deactivate '.Illuminate\Support\Str::singular($title)"
            confirm-label="Deactivate"
        >
            <p>Deactivating <strong>{{ $item->label }}</strong> removes it from selection on new entries. It stays visible on
                @if (isset($item->subcategory_count) && $item->subcategory_count > 0)
                    {{ $item->subcategory_count }} {{ Illuminate\Support\Str::plural('subcategory', $item->subcategory_count) }} and
                @endif
                {{ $item->inventory_item_count }} existing inventory {{ Illuminate\Support\Str::plural('item', $item->inventory_item_count) }}. You can reactivate it later.</p>
        </x-inventory.confirm-action-dialog>
    @endif

    @if (! $item->is_active)
        <x-inventory.confirm-action-dialog
            :dialog-id="$dialogId.'-reactivate-'.$item->id"
            :action="route($reactivateRouteName, $item)"
            method="PATCH"
            :title="'Reactivate '.Illuminate\Support\Str::singular($title)"
            confirm-label="Reactivate"
        >
            <p>Reactivating <strong>{{ $item->label }}</strong> makes it selectable again for new entries.</p>
        </x-inventory.confirm-action-dialog>
    @endif

    @if (! $inUse)
        <x-inventory.confirm-action-dialog
            :dialog-id="$dialogId.'-delete-'.$item->id"
            :action="route($deleteRouteName, $item)"
            method="DELETE"
            :title="'Delete '.Illuminate\Support\Str::singular($title)"
            confirm-label="Delete"
            confirm-variant="danger"
        >
            <p>Deleting <strong>{{ $item->label }}</strong> permanently removes it. It is not used by any inventory items, so this cannot be undone.</p>
        </x-inventory.confirm-action-dialog>
    @endif
@endforeach

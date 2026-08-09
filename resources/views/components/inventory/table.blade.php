@props(['items'])

<div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div class="flex items-center justify-between gap-3 border-b border-slate-200 px-5 py-4">
        <h2 class="text-base font-semibold text-slate-950">Inventory Items</h2>
        <span class="shrink-0 text-sm text-slate-500">
            Showing {{ $items->count() }} of {{ $items->total() }} {{ \Illuminate\Support\Str::plural('item', $items->total()) }}
        </span>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="min-w-56 px-5 py-3">Item Name</th>
                    <th class="px-4 py-3">Category</th>
                    <th class="px-4 py-3 text-right">Current Stock</th>
                    <th class="px-4 py-3">Reserve Level</th>
                    <th class="px-4 py-3">Stock Status</th>
                    <th class="hidden px-4 py-3 lg:table-cell">Last Audited</th>
                    <th class="hidden px-4 py-3 md:table-cell">Last Updated</th>
                    <th class="px-5 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 bg-white">
                @foreach ($items as $item)
                    <x-inventory.table-row :item="$item" />
                @endforeach
            </tbody>
        </table>
    </div>

    @if ($items->hasPages())
        <div class="border-t border-slate-200 px-5 py-3">
            {{ $items->links() }}
        </div>
    @endif
</div>

@foreach ($items as $item)
    @can('adjustStock', $item)
        <x-inventory.adjust-stock-modal :item="$item" />
    @endcan
@endforeach

@props(['items'])

<div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                <tr>
                    <th class="min-w-56 px-4 py-3">Item Name</th>
                    <th class="px-4 py-3">Category</th>
                    <th class="px-4 py-3 text-right">Current Stock</th>
                    <th class="px-4 py-3">Reserve Level</th>
                    <th class="px-4 py-3">Stock Status</th>
                    <th class="hidden px-4 py-3 lg:table-cell">Last Audited</th>
                    <th class="hidden px-4 py-3 md:table-cell">Last Updated</th>
                    <th class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 bg-white">
                @foreach ($items as $item)
                    <x-inventory.table-row :item="$item" />
                @endforeach
            </tbody>
        </table>
    </div>
</div>

@foreach ($items as $item)
    @can('adjustStock', $item)
        <x-inventory.adjust-stock-modal :item="$item" />
    @endcan
@endforeach

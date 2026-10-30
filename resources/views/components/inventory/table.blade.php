@props(['items'])

<x-data-table caption="Inventory records" title="Inventory items" :description="'Showing '.$items->count().' of '.$items->total().' items. Check stock levels and record movements.'" min-width="57rem">
    <x-slot:head>
        <x-data-table.heading>Item</x-data-table.heading>
        <x-data-table.heading align="right">Current stock</x-data-table.heading>
        <x-data-table.heading>Reserve level</x-data-table.heading>
        <x-data-table.heading>Stock status</x-data-table.heading>
        <x-data-table.heading>Updated</x-data-table.heading>
        <x-data-table.heading align="right">Actions</x-data-table.heading>
    </x-slot:head>
    @foreach ($items as $item)<x-inventory.table-row :item="$item" />
    @endforeach
    @if ($items->hasPages())
        <x-slot:footer>{{ $items->links() }}</x-slot:footer>
    @endif
</x-data-table>

@foreach ($items as $item)
    @can('adjustStock', $item)<x-inventory.adjust-stock-modal :item="$item" />
    @endcan
@endforeach

@props(['contributions'])

<x-data-table caption="Donation records" title="Contributions" :description="'Showing '.$contributions->count().' of '.$contributions->total().' donations.'" min-width="55rem">
    <x-slot:head>
        <x-data-table.heading>Donation</x-data-table.heading>
        <x-data-table.heading>Donor</x-data-table.heading>
        <x-data-table.heading align="right">Amount / Items</x-data-table.heading>
        <x-data-table.heading>Status</x-data-table.heading>
        <x-data-table.heading>Received</x-data-table.heading>
        <x-data-table.heading align="right">Actions</x-data-table.heading>
    </x-slot:head>
    @foreach ($contributions as $contribution)
        <x-donations.table-row :contribution="$contribution" />
    @endforeach
    @if ($contributions->hasPages())
        <x-slot:footer>{{ $contributions->links() }}</x-slot:footer>
    @endif
</x-data-table>

@foreach ($contributions as $contribution)
    <x-donations.contribution-details-modal :contribution="$contribution" />
@endforeach

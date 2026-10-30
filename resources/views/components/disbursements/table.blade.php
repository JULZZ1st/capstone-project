@props(['disbursements'])

<x-data-table caption="Fund request records" title="Fund requests" description="Review recipients, amounts, and approval status. Open a request to see its full details." min-width="57rem" scroll-hook="data-fund-request-scroll" data-fund-request-table>
    <x-slot:head>
        <x-data-table.heading>Requested</x-data-table.heading>
        <x-data-table.heading>Recipient / Payee</x-data-table.heading>
        <x-data-table.heading>Purpose</x-data-table.heading>
        <x-data-table.heading align="right">Amount</x-data-table.heading>
        <x-data-table.heading>Status</x-data-table.heading>
        <x-data-table.heading align="right">Actions</x-data-table.heading>
    </x-slot:head>
    @forelse ($disbursements as $disbursement)
        <x-disbursements.table-row :disbursement="$disbursement" />
    @empty
        <x-data-table.empty :columns="6" title="No fund requests found" description="Create a fund request to begin the approval workflow." />
    @endforelse
    @if ($disbursements->hasPages())
        <x-slot:footer>{{ $disbursements->links() }}</x-slot:footer>
    @endif
</x-data-table>

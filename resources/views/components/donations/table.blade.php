@props(['contributions'])

<div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">

    <div class="overflow-x-auto">
        <table class="min-w-[900px] w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-2.5">Receipt No.</th>
                    <th class="px-4 py-2.5">Donor</th>
                    <th class="px-4 py-2.5">Type</th>
                    <th class="px-4 py-2.5">Amount / Items</th>
                    <th class="px-4 py-2.5">Status</th>
                    <th class="whitespace-nowrap px-4 py-2.5">Date Received</th>
                    <th class="hidden px-4 py-2.5 lg:table-cell">Logged by</th>
                    <th class="px-4 py-2.5 text-right">Actions</th>
                </tr>
            </thead>

            <tbody class="divide-y divide-slate-100 bg-white">
                @foreach ($contributions as $contribution)
                    <x-donations.table-row :contribution="$contribution" />
                @endforeach
            </tbody>
        </table>
    </div>

    @if ($contributions->hasPages())
        <div class="border-t border-slate-200 px-5 py-3">
            {{ $contributions->links() }}
        </div>
    @endif

</div>

@foreach ($contributions as $contribution)
    <x-donations.contribution-details-modal :contribution="$contribution" />
@endforeach

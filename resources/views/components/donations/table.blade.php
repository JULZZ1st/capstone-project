@props(['contributions'])

<div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3">Date</th>
                    <th class="px-4 py-3">Donor</th>
                    <th class="px-4 py-3">Type</th>
                    <th class="hidden px-4 py-3 md:table-cell">Campaign</th>
                    <th class="px-4 py-3">Summary</th>
                    <th class="px-4 py-3 text-right">Value</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="hidden px-4 py-3 lg:table-cell">Logged by</th>
                    <th class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 bg-white">
                @foreach ($contributions as $contribution)
                    <x-donations.table-row :contribution="$contribution" />
                @endforeach
            </tbody>
        </table>
    </div>
</div>

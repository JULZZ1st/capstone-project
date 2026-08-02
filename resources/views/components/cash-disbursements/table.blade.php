@props(['disbursements'])

<div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3">Date</th>
                    <th class="px-4 py-3">Recipient</th>
                    <th class="px-4 py-3">Purpose</th>
                    <th class="hidden px-4 py-3 md:table-cell">Payment Method</th>
                    <th class="px-4 py-3 text-right">Amount</th>
                    <th class="hidden px-4 py-3 lg:table-cell">Recorded by</th>
                    <th class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 bg-white">
                @foreach ($disbursements as $disbursement)
                    <x-cash-disbursements.table-row :disbursement="$disbursement" />
                @endforeach
            </tbody>
        </table>
    </div>
</div>

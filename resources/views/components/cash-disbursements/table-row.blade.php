@props(['disbursement'])

@php
    $creatorName = $disbursement->creator?->name
        ?? trim(($disbursement->creator?->first_name ?? '').' '.($disbursement->creator?->last_name ?? ''));
@endphp

<tr @class(['bg-blue-50/60' => session('highlight_cash_disbursement') === $disbursement->id])>
    <td class="whitespace-nowrap px-4 py-4 text-slate-600">{{ optional($disbursement->disbursed_at)->format('M d, Y') }}</td>
    <td class="px-4 py-4">
        <p class="max-w-44 break-words font-medium text-slate-950">{{ $disbursement->disbursed_to }}</p>
        <p class="text-xs text-slate-500">{{ $disbursement->reference_number ?? 'No reference' }}</p>
    </td>
    <td class="px-4 py-4 text-slate-700">{{ config("disbursements.purposes.{$disbursement->purpose}") }}</td>
    <td class="hidden px-4 py-4 text-slate-700 md:table-cell">{{ $disbursement->payment_method ? config("donations.payment_methods.{$disbursement->payment_method}") : 'Not provided' }}</td>
    <td class="whitespace-nowrap px-4 py-4 text-right font-medium text-slate-950">PHP {{ number_format((float) $disbursement->amount, 2) }}</td>
    <td class="hidden px-4 py-4 text-slate-600 lg:table-cell">{{ $creatorName ?: 'System' }}</td>
    <td class="whitespace-nowrap px-4 py-4 text-right">
        <a href="{{ route('cash-disbursements.show', $disbursement) }}" class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">View</a>
    </td>
</tr>

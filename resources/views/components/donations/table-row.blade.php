@props(['contribution'])

@php
    $creatorName = $contribution->creator?->name ?: 'System';
    $value = $contribution->isCash() ? $contribution->amount : $contribution->items_sum_estimated_value;
@endphp

<tr @class(['bg-blue-50/60' => session('highlight_contribution') === $contribution->id])>
    <td>
        <span class="data-table__primary">{{ $contribution->receipt_number ?? '#'.str_pad((string) $contribution->id, 5, '0', STR_PAD_LEFT) }}</span>
        <span class="data-table__secondary">{{ config("donations.types.{$contribution->type}") }}</span>
        @if ($contribution->isCash() && ($contribution->official_receipt_number || $contribution->reference_number))
            <span class="data-table__secondary max-w-52">{{ $contribution->official_receipt_number ? 'OR: '.$contribution->official_receipt_number : 'Bank ref: '.$contribution->reference_number }}</span>
        @endif
    </td>
    <td>
        <span class="data-table__primary">{{ $contribution->donor->full_name }}</span>
        <span class="data-table__secondary">{{ $contribution->donor->contact_number }}</span>
    </td>
    <td class="data-table__number">
        <span class="font-semibold text-slate-950">{{ $value !== null ? 'PHP '.number_format((float) $value, 2) : 'Not provided' }}</span>
        <span class="data-table__secondary">{{ $contribution->isCash() ? $contribution->paymentMethodLabel() : $contribution->items_count.' '.\Illuminate\Support\Str::plural('item', $contribution->items_count) }}</span>
    </td>
    <td><x-donations.status-badge :status="$contribution->status" /></td>
    <td>
        <span class="whitespace-nowrap">{{ $contribution->received_at?->format('M d, Y') ?? 'Not set' }}</span>
        <span class="data-table__secondary max-w-40">Logged by {{ $creatorName }}</span>
    </td>
    <td><div class="data-table__actions"><x-data-table.action :label="'View donation '.$contribution->id" data-dialog-open="view-contribution-{{ $contribution->id }}">View</x-data-table.action></div></td>
</tr>

@props(['contribution'])

@php
    $creatorName = $contribution->creator?->name
        ?? trim(($contribution->creator?->first_name ?? '').' '.($contribution->creator?->last_name ?? ''));
    $value = $contribution->isCash() ? $contribution->amount : $contribution->items_sum_estimated_value;
    $modalId = 'view-contribution-'.$contribution->id;
@endphp

<tr @class(['bg-blue-50/60' => session('highlight_contribution') === $contribution->id, 'hover:bg-slate-50' => session('highlight_contribution') !== $contribution->id])>
    <td class="whitespace-nowrap px-4 py-3 align-top"><p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">Donation ID</p><p class="font-semibold text-slate-950">{{ $contribution->receipt_number ?? '#'.str_pad((string) $contribution->id, 5, '0', STR_PAD_LEFT) }}</p>@if ($contribution->isCash())<p class="text-xs text-slate-500">{{ $contribution->official_receipt_number ? 'Official Receipt: '.$contribution->official_receipt_number : ($contribution->reference_number ? 'Bank Ref: '.$contribution->reference_number : 'No external reference') }}</p>@endif</td>
    <td class="px-4 py-3 align-top"><p class="max-w-44 break-words font-medium text-slate-950">{{ $contribution->donor->full_name }}</p><p class="text-xs text-slate-500">{{ $contribution->donor->contact_number }}</p></td>
    <td class="px-4 py-3 align-top text-slate-700">{{ config("donations.types.{$contribution->type}") }}</td>
    <td class="px-4 py-3 align-top"><p class="font-medium text-slate-950">{{ $value ? 'PHP '.number_format((float) $value, 2) : 'Not provided' }}</p><p class="text-xs text-slate-500">{{ $contribution->isCash() ? $contribution->paymentMethodLabel() : $contribution->items_count.' '.\Illuminate\Support\Str::plural('item', $contribution->items_count) }}</p></td>
    <td class="px-4 py-3 align-top"><x-donations.status-badge :status="$contribution->status" /></td>
    <td class="whitespace-nowrap px-4 py-3 align-top text-slate-600">{{ optional($contribution->received_at)->format('M d, Y') ?? 'Not set' }}</td>
    <td class="hidden px-4 py-3 align-top text-slate-600 lg:table-cell">{{ $creatorName ?: 'System' }}</td>
    <td class="whitespace-nowrap px-4 py-3 text-right align-top"><button type="button" class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50" data-dialog-open="{{ $modalId }}">View</button></td>
</tr>

@props(['contribution'])

@php
    $creatorName = $contribution->creator?->name
        ?? trim(($contribution->creator?->first_name ?? '').' '.($contribution->creator?->last_name ?? ''));
    $value = $contribution->isCash()
        ? $contribution->amount
        : $contribution->items_sum_estimated_value;
@endphp

<tr @class(['bg-blue-50/60' => session('highlight_contribution') === $contribution->id, 'hover:bg-slate-50' => session('highlight_contribution') !== $contribution->id])>
    <td class="whitespace-nowrap px-5 py-4 align-top">
        <p class="font-semibold text-slate-950">#{{ str_pad((string) $contribution->id, 5, '0', STR_PAD_LEFT) }}</p>
        @if ($contribution->isCash())
            <p class="text-xs text-slate-500">{{ $contribution->reference_number ?? 'No reference' }}</p>
        @endif
    </td>
    <td class="px-4 py-4 align-top">
        <p class="max-w-44 break-words font-medium text-slate-950">{{ $contribution->donor->full_name }}</p>
        <p class="text-xs text-slate-500">{{ $contribution->donor->contact_number }}</p>
    </td>
    <td class="px-4 py-4 align-top text-slate-700">{{ config("donations.types.{$contribution->type}") }}</td>
    <td class="hidden px-4 py-4 align-top text-slate-700 md:table-cell">{{ $contribution->campaign?->name ?? 'No campaign' }}</td>
    <td class="px-4 py-4 align-top">
        <p class="font-medium text-slate-950">{{ $value ? 'PHP '.number_format((float) $value, 2) : 'Not provided' }}</p>
        @if ($contribution->isCash())
            <p class="text-xs text-slate-500">{{ config("donations.payment_methods.{$contribution->payment_method}") }}</p>
        @else
            <p class="text-xs text-slate-500">{{ $contribution->items_count }} {{ \Illuminate\Support\Str::plural('item', $contribution->items_count) }}</p>
        @endif
    </td>
    <td class="px-4 py-4 align-top"><x-donations.status-badge :status="$contribution->status" /></td>
    <td class="whitespace-nowrap px-4 py-4 align-top text-slate-600">{{ optional($contribution->received_at)->format('M d, Y') ?? 'Not set' }}</td>
    <td class="hidden px-4 py-4 align-top text-slate-600 lg:table-cell">{{ $creatorName ?: 'System' }}</td>
    <td class="whitespace-nowrap px-5 py-4 text-right align-top">
        <div class="flex justify-end gap-2">
            <a href="{{ route('donations.show', $contribution) }}" class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">View</a>
            @can('verify', $contribution)
                <form method="POST" action="{{ route('donations.verify', $contribution) }}">
                    @csrf
                    @method('PATCH')
                    <button class="rounded-md bg-emerald-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-800">Verify</button>
                </form>
            @endcan
        </div>
    </td>
</tr>

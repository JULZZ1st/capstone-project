@props(['contribution'])

@php
    $creatorName = $contribution->creator?->name
        ?? trim(($contribution->creator?->first_name ?? '').' '.($contribution->creator?->last_name ?? ''));
    $value = $contribution->isCash()
        ? $contribution->amount
        : $contribution->items_sum_estimated_value;
@endphp

<tr @class(['bg-blue-50/60' => session('highlight_contribution') === $contribution->id])>
    <td class="whitespace-nowrap px-4 py-4 text-slate-600">{{ optional($contribution->received_at)->format('M d, Y') ?? 'Not set' }}</td>
    <td class="px-4 py-4">
        <p class="max-w-44 break-words font-medium text-slate-950">{{ $contribution->donor->full_name }}</p>
        <p class="text-xs text-slate-500">{{ $contribution->donor->contact_number }}</p>
    </td>
    <td class="px-4 py-4 text-slate-700">{{ config("donations.types.{$contribution->type}") }}</td>
    <td class="hidden px-4 py-4 text-slate-700 md:table-cell">{{ $contribution->campaign?->name ?? 'No campaign' }}</td>
    <td class="px-4 py-4 text-slate-700">
        @if ($contribution->isCash())
            <p>{{ config("donations.payment_methods.{$contribution->payment_method}") }}</p>
            <p class="text-xs text-slate-500">{{ $contribution->reference_number ?? 'No reference' }}</p>
        @else
            <p>{{ $contribution->items_count }} item{{ $contribution->items_count === 1 ? '' : 's' }}</p>
            <p class="text-xs text-slate-500">Estimated total</p>
        @endif
    </td>
    <td class="whitespace-nowrap px-4 py-4 text-right font-medium text-slate-950">
        {{ $value ? 'PHP '.number_format((float) $value, 2) : 'Not provided' }}
    </td>
    <td class="px-4 py-4"><x-donations.status-badge :status="$contribution->status" /></td>
    <td class="hidden px-4 py-4 text-slate-600 lg:table-cell">{{ $creatorName ?: 'System' }}</td>
    <td class="whitespace-nowrap px-4 py-4 text-right">
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

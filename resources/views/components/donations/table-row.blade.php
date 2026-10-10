@props(['contribution'])

@php
    $creatorName = $contribution->creator?->name
        ?? trim(($contribution->creator?->first_name ?? '').' '.($contribution->creator?->last_name ?? ''));

    $value = $contribution->isCash()
        ? $contribution->amount
        : $contribution->items_sum_estimated_value;

    $modalId = 'view-contribution-'.$contribution->id;
    $updateModalId = 'update-contribution-'.$contribution->id;
@endphp

<tr @class([
    'bg-blue-50/60' => session('highlight_contribution') === $contribution->id,
    'hover:bg-slate-50' => session('highlight_contribution') !== $contribution->id
])>
    <td class="whitespace-nowrap px-5 py-4 align-top">
        <p class="font-semibold text-slate-950">
            {{ $contribution->receipt_number ?? '#'.str_pad((string) $contribution->id, 5, '0', STR_PAD_LEFT) }}
        </p>

        @if ($contribution->isCash())
            <p class="text-xs text-slate-500">
                {{ $contribution->reference_number ? 'Ref: '.$contribution->reference_number : 'No external reference' }}
            </p>
        @endif
    </td>

    <td class="px-4 py-4 align-top">
        <p class="max-w-44 break-words font-medium text-slate-950">
            {{ $contribution->donor->full_name }}
        </p>

        <p class="text-xs text-slate-500">
            {{ $contribution->donor->contact_number }}
        </p>
    </td>

    <td class="px-4 py-4 align-top text-slate-700">
        {{ config("donations.types.{$contribution->type}") }}
    </td>

    <td class="hidden px-4 py-4 align-top text-slate-700 md:table-cell">
        {{ $contribution->campaign?->name ?? 'No campaign' }}
    </td>

    <td class="px-4 py-4 align-top">
        <p class="font-medium text-slate-950">
            {{ $value ? 'PHP '.number_format((float) $value, 2) : 'Not provided' }}
        </p>

        @if ($contribution->isCash())
            <p class="text-xs text-slate-500">
                {{ config("donations.payment_methods.{$contribution->payment_method}") }}
            </p>
        @else
            <p class="text-xs text-slate-500">
                {{ $contribution->items_count }}
                {{ \Illuminate\Support\Str::plural('item', $contribution->items_count) }}
            </p>
        @endif
    </td>

    <td class="px-4 py-4 align-top">
        <x-donations.status-badge :status="$contribution->status" />
    </td>

    <td class="whitespace-nowrap px-4 py-4 align-top text-slate-600">
        {{ optional($contribution->received_at)->format('M d, Y') ?? 'Not set' }}
    </td>

    <td class="hidden px-4 py-4 align-top text-slate-600 lg:table-cell">
        {{ $creatorName ?: 'System' }}
    </td>

    <td class="whitespace-nowrap px-5 py-4 text-right align-top">
        <div class="flex justify-end gap-2">
            <button
                type="button"
                class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                onclick="document.getElementById('{{ $modalId }}').showModal()"
            >
                View
            </button>

            @can('verify', $contribution)
                <form method="POST" action="{{ route('donations.verify', $contribution) }}">
                    @csrf
                    @method('PATCH')

                    <button
                        class="rounded-md bg-emerald-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-800"
                    >
                        Verify
                    </button>
                </form>
            @endcan
        </div>
    </td>
</tr>

<dialog
    id="{{ $modalId }}"
    class="w-[min(1000px,calc(100vw-2rem))] rounded-2xl p-0 shadow-xl backdrop:bg-slate-950/50"
>
    <div class="flex max-h-[90vh] flex-col">
        <div class="flex shrink-0 items-center justify-between border-b border-slate-200 px-5 py-4">
            <div>
                <h2 class="text-lg font-semibold text-slate-950">
                    Contribution Details
                </h2>

                <p class="text-sm text-slate-500">
                    {{ $contribution->receipt_number ?? '#'.str_pad((string) $contribution->id, 5, '0', STR_PAD_LEFT) }}
                </p>
            </div>

            <button
                type="button"
                class="text-slate-400 hover:text-slate-600"
                onclick="document.getElementById('{{ $modalId }}').close()"
            >
                ✕
            </button>
        </div>

        <div class="overflow-y-auto px-5 py-5">
            <div class="grid gap-6 md:grid-cols-2">
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-500">
                        Donor
                    </p>

                    <p class="mt-1 font-semibold text-slate-950">
                        {{ $contribution->donor->full_name }}
                    </p>

                    <p class="text-sm text-slate-500">
                        {{ $contribution->donor->contact_number }}
                    </p>
                </div>

                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-500">
                        Contribution Type
                    </p>

                    <p class="mt-1 font-semibold text-slate-950">
                        {{ config("donations.types.{$contribution->type}") }}
                    </p>
                </div>

                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-500">
                        Receipt Number
                    </p>

                    <p class="mt-1 text-slate-700">
                        {{ $contribution->receipt_number ?? 'Not provided' }}
                    </p>
                </div>

                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-500">
                        Date Received
                    </p>

                    <p class="mt-1 text-slate-700">
                        {{ optional($contribution->received_at)->format('M d, Y') ?? 'Not set' }}
                    </p>
                </div>

                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-500">
                        Campaign
                    </p>

                    <p class="mt-1 text-slate-700">
                        {{ $contribution->campaign?->name ?? 'No campaign' }}
                    </p>
                </div>

                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-500">
                        Logged By
                    </p>

                    <p class="mt-1 text-slate-700">
                        {{ $creatorName ?: 'System' }}
                    </p>
                </div>

                @if ($contribution->isCash())
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-slate-500">
                            Amount
                        </p>

                        <p class="mt-1 font-semibold text-slate-950">
                            PHP {{ number_format((float) $contribution->amount, 2) }}
                        </p>
                    </div>

                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-slate-500">
                            Payment Method
                        </p>

                        <p class="mt-1 text-slate-700">
                            {{ config("donations.payment_methods.{$contribution->payment_method}") }}
                        </p>
                    </div>

                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-slate-500">
                            Reference Number
                        </p>

                        <p class="mt-1 text-slate-700">
                            {{ $contribution->reference_number ?? 'Not provided' }}
                        </p>
                    </div>
                @else
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-slate-500">
                            Estimated Value
                        </p>

                        <p class="mt-1 font-semibold text-slate-950">
                            {{ $value ? 'PHP '.number_format((float) $value, 2) : 'Not provided' }}
                        </p>
                    </div>

                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-slate-500">
                            Items
                        </p>

                        <p class="mt-1 text-slate-700">
                            {{ $contribution->items_count }}
                            {{ \Illuminate\Support\Str::plural('item', $contribution->items_count) }}
                        </p>
                    </div>
                @endif

                <div class="md:col-span-2">
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-500">
                        Status
                    </p>

                    <div class="mt-1">
                        <x-donations.status-badge :status="$contribution->status" />
                    </div>
                </div>

                @if ($contribution->notes)
                    <div class="md:col-span-2">
                        <p class="text-xs font-medium uppercase tracking-wide text-slate-500">
                            Notes
                        </p>

                        <p class="mt-1 whitespace-pre-line text-slate-700">
                            {{ $contribution->notes }}
                        </p>
                    </div>
                @endif
            </div>
        </div>

        <div class="flex shrink-0 justify-end gap-3 border-t border-slate-200 px-5 py-4">
            <button
                type="button"
                class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                onclick="document.getElementById('{{ $modalId }}').close()"
            >
                Close
            </button>

            <button
                type="button"
                class="rounded-md bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800"
                onclick="document.getElementById('{{ $updateModalId }}').showModal()"
            >
                Update Record
            </button>
        </div>
    </div>
</dialog>

<dialog
    id="{{ $updateModalId }}"
    class="w-[min(520px,calc(100vw-2rem))] rounded-2xl p-0 shadow-xl backdrop:bg-slate-950/50"
>
    <form
        method="POST"
        action="{{ route('donations.update', $contribution) }}"
        class="flex max-h-[90vh] flex-col"
    >
        @csrf
        @method('PATCH')

        <div class="flex shrink-0 items-center justify-between border-b border-slate-200 px-5 py-4">
            <div>
                <h2 class="text-lg font-semibold text-slate-950">
                    Update Contribution
                </h2>

                <p class="text-sm text-slate-500">
                    {{ $contribution->receipt_number ?? '#'.str_pad((string) $contribution->id, 5, '0', STR_PAD_LEFT) }}
                </p>
            </div>

            <button
                type="button"
                class="text-slate-400 hover:text-slate-600"
                onclick="document.getElementById('{{ $updateModalId }}').close()"
            >
                ✕
            </button>
        </div>

        <div class="space-y-5 overflow-y-auto px-5 py-5">
            <div>
                <x-form.label for="status-{{ $contribution->id }}">
                    Status
                </x-form.label>

                <select
                    id="status-{{ $contribution->id }}"
                    name="status"
                    class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                    required
                >
                    @foreach (['pending', 'received', 'verified', 'rejected'] as $status)
                        <option
                            value="{{ $status }}"
                            @selected($contribution->status === $status)
                        >
                            {{ ucfirst($status) }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-form.label for="notes-{{ $contribution->id }}">
                    Notes
                </x-form.label>

                <textarea
                    id="notes-{{ $contribution->id }}"
                    name="notes"
                    rows="4"
                    class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                >{{ $contribution->notes }}</textarea>
            </div>
        </div>

        <div class="flex shrink-0 justify-end gap-3 border-t border-slate-200 px-5 py-4">
            <button
                type="button"
                class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                onclick="document.getElementById('{{ $updateModalId }}').close()"
            >
                Close
            </button>

            <x-button type="submit">
                Save Update
            </x-button>
        </div>
    </form>
</dialog>
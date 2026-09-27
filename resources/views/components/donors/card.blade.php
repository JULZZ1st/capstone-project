@props(['donor'])

@php
    $notProvided = 'Not provided';
    $money = fn ($amount) => 'PHP '.number_format((float) ($amount ?? 0), 2);
@endphp

<article class="flex h-full flex-col rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
    <div class="flex items-start justify-between gap-4">
        <div class="min-w-0">
            <h2 class="truncate text-lg font-semibold text-slate-950">{{ $donor->full_name }}</h2>
            <p class="mt-1 text-sm font-medium text-green-700">{{ config('donors.types')[$donor->donor_type] ?? ucfirst($donor->donor_type) }}</p>
        </div>
        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700">
            {{ config('donors.statuses')[$donor->status] ?? ucfirst($donor->status) }}
        </span>
    </div>

    <dl class="mt-4 space-y-2 text-sm">
        <div>
            <dt class="text-slate-500">Email</dt>
            <dd class="break-words font-medium text-slate-900">{{ $donor->email ?: $notProvided }}</dd>
        </div>
        <div>
            <dt class="text-slate-500">Contact Number</dt>
            <dd class="font-medium text-slate-900">{{ $donor->contact_number }}</dd>
        </div>
        <div>
            <dt class="text-slate-500">Address</dt>
            <dd class="line-clamp-2 font-medium text-slate-900">{{ $donor->address }}</dd>
        </div>
    </dl>

    <div class="mt-5 grid gap-3 sm:grid-cols-3">
        <div class="rounded-md bg-slate-50 p-3">
            <p class="text-xs text-slate-500">Cash</p>
            <p class="mt-1 text-sm font-semibold text-slate-950">{{ $money($donor->total_cash_donations) }}</p>
        </div>
        <div class="rounded-md bg-slate-50 p-3">
            <p class="text-xs text-slate-500">In-Kind</p>
            <p class="mt-1 text-sm font-semibold text-slate-950">{{ $money($donor->total_in_kind_donations) }}</p>
        </div>
        <div class="rounded-md bg-slate-50 p-3">
            <p class="text-xs text-slate-500">Donations</p>
            <p class="mt-1 text-sm font-semibold text-slate-950">{{ number_format($donor->donations_count) }}</p>
        </div>
    </div>

    <div class="mt-auto flex flex-wrap gap-2 pt-5">
        <a href="{{ route('donors.show', $donor) }}" class="inline-flex items-center justify-center rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
            View History
        </a>
        <button type="button" class="inline-flex items-center justify-center rounded-md bg-blue-700 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-800" data-dialog-open="edit-donor-modal-{{ $donor->id }}">
            Edit Profile
        </button>
        <form method="POST" action="{{ route('donors.destroy', $donor) }}" onsubmit="return confirm('Remove this donor? If they have donation history, the profile will be deactivated instead of permanently deleted.')">
            @csrf
            @method('DELETE')
            <button type="submit" class="inline-flex items-center justify-center rounded-md border border-red-300 px-3 py-2 text-sm font-semibold text-red-700 hover:bg-red-50">
                Remove
            </button>
        </form>
    </div>
</article>

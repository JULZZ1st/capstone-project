@extends('layouts.app')

@section('title', 'Suggested Donors')
@section('page-title', 'Suggested Donors')
@section('page-subtitle', 'Review donor history for inventory replenishment.')

@section('content')
    <div class="mx-auto max-w-6xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <a href="{{ route('inventory.index') }}" class="text-sm font-semibold text-violet-700 hover:text-violet-900">← Back to inventory</a>
                <h1 class="mt-2 text-2xl font-bold text-slate-950">Suggested Donors</h1>
                <p class="mt-1 text-sm text-slate-500">Recommendations use recorded, received in-kind donations in this workspace.</p>
            </div>
            <span class="inline-flex w-fit rounded-full px-3 py-1.5 text-sm font-semibold {{ $alert->status === 'active' ? 'bg-amber-100 text-amber-800' : ($alert->status === 'resolved' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-700') }}">
                {{ \Illuminate\Support\Str::headline($alert->status) }}
            </span>
        </div>

        @if (session('success'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">{{ session('success') }}</div>
        @endif

        <section class="rounded-2xl border border-violet-100 bg-gradient-to-r from-violet-50 to-white p-5 shadow-sm sm:p-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-violet-700">{{ $inventoryItem->current_quantity <= 0 ? 'Out of stock' : 'Low stock' }}</p>
                    <h2 class="mt-1 text-xl font-bold text-slate-950">{{ $inventoryItem->name }}</h2>
                    <p class="mt-1 text-sm text-slate-600">{{ \Illuminate\Support\Str::headline(str_replace('_', ' ', $inventoryItem->category)) }}</p>
                </div>
                <div class="grid grid-cols-2 gap-3 sm:min-w-64">
                    <div class="rounded-xl bg-white p-3 ring-1 ring-slate-200"><p class="text-xs text-slate-500">Current quantity</p><p class="mt-1 font-bold text-slate-900">{{ number_format((float) $inventoryItem->current_quantity, 2) }} {{ config('inventory.units.'.$inventoryItem->unit, $inventoryItem->unit) }}</p></div>
                    <div class="rounded-xl bg-white p-3 ring-1 ring-slate-200"><p class="text-xs text-slate-500">Reorder level</p><p class="mt-1 font-bold text-slate-900">{{ number_format((float) $inventoryItem->minimum_reserve_quantity, 2) }} {{ config('inventory.units.'.$inventoryItem->unit, $inventoryItem->unit) }}</p></div>
                </div>
            </div>
        </section>

        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div><h2 class="text-lg font-bold text-slate-950">Donor recommendations</h2><p class="text-sm text-slate-500">Sorted by match strength, donation frequency, then most recent donation.</p></div>
            @if ($alert->status !== 'resolved')
                <div class="flex flex-wrap gap-2">
                    <form method="POST" action="{{ route('inventory.donors.suggested.update', $inventoryItem) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="handled"><button class="rounded-lg bg-violet-700 px-3 py-2 text-sm font-semibold text-white hover:bg-violet-800">Mark handled</button></form>
                    <form method="POST" action="{{ route('inventory.donors.suggested.update', $inventoryItem) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="dismissed"><button class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Dismiss alert</button></form>
                </div>
            @endif
        </div>

        @if (! $has_history)
            <div class="rounded-2xl border border-slate-200 bg-white p-6 text-center shadow-sm">
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-violet-100 text-violet-700"><x-icon name="users" class="h-6 w-6" /></div>
                <h3 class="mt-3 font-semibold text-slate-900">No matching donor history is available</h3>
                <p class="mt-1 text-sm text-slate-500">There are no recorded received in-kind donations for this item or its category yet.</p>
            </div>
        @else
            <div class="grid gap-4 md:grid-cols-2">
                @foreach ($recommendations as $donor)
                    <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0"><h3 class="truncate text-lg font-bold text-slate-950">{{ $donor->full_name }}</h3><p class="mt-0.5 text-sm text-slate-500">{{ config('donors.types.'.$donor->donor_type, \Illuminate\Support\Str::headline($donor->donor_type)) }}</p></div>
                            <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-bold {{ $donor->match_level === 'Strong Match' ? 'bg-emerald-100 text-emerald-800' : 'bg-violet-100 text-violet-800' }}">{{ $donor->match_level }}</span>
                        </div>
                        @if ($donor->contact_person)<p class="mt-3 text-sm text-slate-700"><span class="font-semibold">Contact person:</span> {{ $donor->contact_person }}</p>@endif
                        <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-sm">
                            @if ($donor->contact_number)<a class="font-medium text-violet-700 hover:underline" href="tel:{{ $donor->contact_number }}">{{ $donor->contact_number }}</a>@endif
                            @if ($donor->email)<a class="break-all font-medium text-violet-700 hover:underline" href="mailto:{{ $donor->email }}">{{ $donor->email }}</a>@endif
                        </div>
                        <a href="{{ route('donors.show', $donor->id) }}" class="mt-3 inline-flex text-sm font-semibold text-violet-700 hover:text-violet-900 hover:underline">View donor details</a>
                        <div class="mt-4 rounded-xl bg-slate-50 p-3 text-sm">
                            <p class="font-semibold text-slate-800">{{ $donor->reason }}</p>
                            <p class="mt-1 text-slate-600">{{ $donor->donation_count }} matching donation{{ $donor->donation_count === 1 ? '' : 's' }} · Last donation {{ $donor->last_donation_at ? \Illuminate\Support\Carbon::parse($donor->last_donation_at)->format('M j, Y') : 'date unavailable' }}</p>
                            <p class="mt-2 text-xs text-slate-500">Previously donated: {{ $donor->previous_items->join(', ') }}</p>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
        <p class="text-xs text-slate-500">Contact details are provided for an administrator to follow up. No messages are sent automatically.</p>
    </div>
@endsection

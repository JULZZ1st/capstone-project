@extends('layouts.app')

@section('title', 'Fund Disbursements')
@section('page-title', 'Fund Disbursements')
@section('page-subtitle', 'Submit and review cash requests with maker-checker approval.')

@section('content')
    <div class="space-y-6">

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-base font-semibold text-slate-950">Request a disbursement</h2>
            <form method="POST" action="{{ route('disbursements.store') }}" class="mt-4 grid gap-4 md:grid-cols-[12rem_1fr_auto] md:items-end">
                @csrf
                <x-form.input name="amount" label="Amount" type="number" step="0.01" min="0.01" />
                <div>
                    <x-form.label for="purpose">Purpose</x-form.label>
                    <textarea id="purpose" name="purpose" rows="2" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">{{ old('purpose') }}</textarea>
                    <x-form.error name="purpose" />
                </div>
                <x-button>Submit Request</x-button>
            </form>
        </section>

        <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-5 py-4">
                <h2 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Request history</h2>
            </div>
            <div class="divide-y divide-slate-100">
                @forelse ($disbursements as $disbursement)
                    <article class="flex flex-col gap-4 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="font-semibold text-slate-950">PHP {{ number_format((float) $disbursement->amount, 2) }}</p>
                            <p class="mt-1 text-sm text-slate-600">{{ $disbursement->purpose }}</p>
                            <p class="mt-1 text-xs text-slate-500">Requested by {{ $disbursement->requester->name }} on {{ $disbursement->created_at->format('M d, Y') }}</p>
                                @if ($disbursement->isVoided())
                                    <p class="mt-1 text-xs font-semibold text-red-700">Voided: {{ $disbursement->void_reason }}</p>
                                @endif
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold uppercase text-slate-700">{{ $disbursement->status }}</span>
                            @can('approve', $disbursement)
                                <form method="POST" action="{{ route('disbursements.approve', $disbursement) }}">
                                    @csrf @method('PATCH')
                                    <x-button>Approve</x-button>
                                </form>
                                <details class="relative">
                                    <summary class="cursor-pointer rounded-md bg-red-100 px-2 py-1 text-xs font-semibold text-red-700">Reject</summary>
                                    <form method="POST" action="{{ route('disbursements.reject', $disbursement) }}" class="absolute right-0 z-10 mt-2 w-72 space-y-2 rounded border border-slate-100 bg-white p-3 shadow-lg">
                                        @csrf @method('PATCH')
                                        <x-form.label for="rejection_reason_{{ $disbursement->id }}">Rejection reason</x-form.label>
                                        <textarea id="rejection_reason_{{ $disbursement->id }}" name="rejection_reason" rows="2" required class="block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-red-500 focus:ring-red-500"></textarea>
                                        <x-button class="w-full">Confirm Rejection</x-button>
                                    </form>
                                </details>
                            @endcan
                            @can('void', $disbursement)
                                <details class="relative">
                                    <summary class="cursor-pointer rounded-md bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700">Void</summary>
                                    <form method="POST" action="{{ route('disbursements.void', $disbursement) }}" class="absolute right-0 z-10 mt-2 w-72 space-y-2 rounded border border-slate-100 bg-white p-3 shadow-lg">
                                        @csrf @method('PATCH')
                                        <x-form.label for="void_reason_{{ $disbursement->id }}">Void reason</x-form.label>
                                        <textarea id="void_reason_{{ $disbursement->id }}" name="void_reason" rows="2" required class="block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-red-500 focus:ring-red-500"></textarea>
                                        <x-button class="w-full">Confirm Void</x-button>
                                    </form>
                                </details>
                            @endcan
                        </div>
                    </article>
                @empty
                    <x-dashboard.empty-state message="No disbursement requests have been submitted." class="m-5" />
                @endforelse
            </div>
        </section>
    </div>
@endsection

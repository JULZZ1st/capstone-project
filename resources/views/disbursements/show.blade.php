@extends('layouts.app')

@section('title', 'Disbursement Request')
@section('page-title', 'Fund Disbursements')
@section('page-subtitle', 'Review request details before approval or rejection.')

@section('content')
    <div class="space-y-6">
        <div>
            <a href="{{ route('disbursements.index') }}" class="text-sm font-semibold text-blue-700 hover:text-blue-800">Back to request history</a>
        </div>

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-3 border-b border-slate-100 pb-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h2 class="text-base font-semibold text-slate-950">Disbursement Request #{{ $disbursement->id }}</h2>
                    <p class="mt-1 text-sm text-slate-600">{{ $disbursement->purpose }}</p>
                </div>
                <span class="w-fit rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold uppercase text-slate-700">{{ $disbursement->status }}</span>
            </div>

            <dl class="mt-5 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Requested Date</dt>
                    <dd class="mt-1 text-sm font-medium text-slate-950">{{ ($disbursement->requested_date ?? $disbursement->created_at)->format('M d, Y') }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Recipient/Payee</dt>
                    <dd class="mt-1 text-sm font-medium text-slate-950">{{ $disbursement->recipient ?? 'Not provided' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Amount</dt>
                    <dd class="mt-1 text-sm font-medium text-slate-950">PHP {{ number_format((float) $disbursement->amount, 2) }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Requested By</dt>
                    <dd class="mt-1 text-sm font-medium text-slate-950">{{ $disbursement->requester?->name ?? 'Unknown requester' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Supporting Reference</dt>
                    <dd class="mt-1 text-sm font-medium text-slate-950">{{ $disbursement->supporting_reference ?? 'Not provided' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Submitted</dt>
                    <dd class="mt-1 text-sm font-medium text-slate-950">{{ $disbursement->created_at->format('M d, Y h:i A') }}</dd>
                </div>
            </dl>

            <div class="mt-5">
                <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Notes</h3>
                <p class="mt-1 whitespace-pre-line text-sm text-slate-700">{{ $disbursement->notes ?? 'Not provided' }}</p>
            </div>

            @if ($disbursement->approved_by)
                <p class="mt-5 text-sm text-slate-600">Approved by {{ $disbursement->approver?->name ?? 'Unknown approver' }} on {{ $disbursement->approved_at?->format('M d, Y h:i A') }}.</p>
            @endif

            @if ($disbursement->rejection_reason)
                <p class="mt-5 text-sm font-semibold text-red-700">Rejected: {{ $disbursement->rejection_reason }}</p>
            @endif

            @if ($disbursement->isVoided())
                <p class="mt-5 text-sm font-semibold text-red-700">Voided by {{ $disbursement->voider?->name ?? 'Unknown user' }}: {{ $disbursement->void_reason }}</p>
            @endif
        </section>

        @can('approve', $disbursement)
            <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-base font-semibold text-slate-950">Admin decision</h2>
                <div class="mt-4 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <form method="POST" action="{{ route('disbursements.approve', $disbursement) }}">
                        @csrf @method('PATCH')
                        <x-button>Approve</x-button>
                    </form>

                    <form method="POST" action="{{ route('disbursements.reject', $disbursement) }}" class="w-full space-y-2 lg:max-w-md">
                        @csrf @method('PATCH')
                        <x-form.label for="rejection_reason">Rejection reason</x-form.label>
                        <textarea id="rejection_reason" name="rejection_reason" rows="3" required class="block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-red-500 focus:ring-red-500">{{ old('rejection_reason') }}</textarea>
                        <x-form.error name="rejection_reason" />
                        <x-button class="w-full">Reject</x-button>
                    </form>
                </div>
            </section>
        @endcan
    </div>
@endsection

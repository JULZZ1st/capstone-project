@extends('layouts.app')

@section('title', 'Disbursement Details')
@section('page-title', 'Cash Disbursements')

@section('content')
    <div class="space-y-6">
        <div>
            <a href="{{ route('cash-disbursements.index') }}" class="text-sm font-semibold text-blue-700 hover:text-blue-800">Back to disbursements</a>
            <h1 class="mt-2 text-2xl font-semibold text-slate-950">{{ $cashDisbursement->disbursed_to }}</h1>
            <p class="mt-1 text-sm text-slate-600">{{ config("disbursements.purposes.{$cashDisbursement->purpose}") }}</p>
        </div>

        <section class="rounded-lg border border-slate-200 bg-white p-5">
            <dl class="grid gap-4 sm:grid-cols-2">
                <div>
                    <dt class="text-xs font-semibold uppercase text-slate-500">Date disbursed</dt>
                    <dd class="mt-1 text-sm text-slate-900">{{ optional($cashDisbursement->disbursed_at)->format('M d, Y h:i A') }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase text-slate-500">Recorded by</dt>
                    <dd class="mt-1 text-sm text-slate-900">{{ $cashDisbursement->creator?->name ?? trim(($cashDisbursement->creator?->first_name ?? '').' '.($cashDisbursement->creator?->last_name ?? '')) ?: 'System' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase text-slate-500">Payment method</dt>
                    <dd class="mt-1 text-sm text-slate-900">{{ $cashDisbursement->payment_method ? config("donations.payment_methods.{$cashDisbursement->payment_method}") : 'Not provided' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase text-slate-500">Reference</dt>
                    <dd class="mt-1 text-sm text-slate-900">{{ $cashDisbursement->reference_number ?? 'Not provided' }}</dd>
                </div>
            </dl>

            <div class="mt-6 rounded-md bg-slate-50 p-4">
                <p class="text-sm font-semibold text-slate-600">Amount</p>
                <p class="mt-1 text-2xl font-bold text-slate-950">PHP {{ number_format((float) $cashDisbursement->amount, 2) }}</p>
            </div>

            @if ($cashDisbursement->notes)
                <div class="mt-6">
                    <h3 class="text-sm font-semibold text-slate-900">Notes</h3>
                    <p class="mt-2 whitespace-pre-line text-sm text-slate-600">{{ $cashDisbursement->notes }}</p>
                </div>
            @endif
        </section>
    </div>
@endsection

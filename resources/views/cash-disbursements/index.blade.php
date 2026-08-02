@extends('layouts.app')

@section('title', 'Cash Disbursements')
@section('page-title', 'Cash Disbursements')

@section('content')
    @if ($errors->any())
        <x-alert type="error" class="mb-6">Please review the highlighted fields and try again.</x-alert>
    @endif

    <div class="space-y-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-slate-950">Cash Disbursements</h1>
                <p class="mt-1 text-sm text-slate-600">Record outgoing cash payments such as aid payouts and operational expenses.</p>
            </div>
            <x-button type="button" class="sm:w-auto" data-dialog-open="log-disbursement-dialog">
                Record Disbursement
            </x-button>
        </div>

        <x-cash-disbursements.filter-bar :filters="$filters" />

        @if ($disbursements->count())
            <x-cash-disbursements.table :disbursements="$disbursements" />
            {{ $disbursements->links() }}
        @else
            <div class="rounded-lg border border-slate-200 bg-white p-8">
                <x-dashboard.empty-state :message="request()->hasAny(['search', 'purpose', 'from', 'to'])
                    ? 'No disbursements match the current filters.'
                    : 'No cash disbursements have been recorded yet.'" />
                <div class="mt-4 flex justify-center gap-3">
                    @if (request()->hasAny(['search', 'purpose', 'from', 'to']))
                        <a href="{{ route('cash-disbursements.index') }}" class="text-sm font-semibold text-blue-700 hover:text-blue-800">Clear filters</a>
                    @endif
                    <button type="button" class="text-sm font-semibold text-blue-700 hover:text-blue-800" data-dialog-open="log-disbursement-dialog">
                        Record Disbursement
                    </button>
                </div>
            </div>
        @endif
    </div>

    <x-cash-disbursements.log-modal />
@endsection

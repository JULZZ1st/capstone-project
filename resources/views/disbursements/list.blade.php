@extends('layouts.app')

@section('title', 'Fund Requests')
@section('page-title', 'Fund Requests')
@section('page-subtitle', 'Track requests from submission through approval and payment.')

@section('content')
    <div class="space-y-4">
        @can('create', \App\Models\CashDisbursement::class)
            <div class="flex justify-end">
                <a href="{{ route('disbursements.create') }}" class="inline-flex min-h-10 items-center justify-center rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-200">Create Fund Request</a>
            </div>
        @endcan
        <div data-fund-request-results>
            <x-disbursements.table :disbursements="$disbursements" />
        </div>
    </div>
@endsection

@extends('layouts.app')

@section('title', 'Create Fund Request')
@section('page-title', 'Create Fund Request')
@section('page-subtitle', 'Submit a request for review and approval.')

@section('content')
    <div class="mx-auto max-w-4xl space-y-4">
        <div>
            <a href="{{ route('disbursements.index') }}" class="inline-flex min-h-10 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:border-blue-300 hover:bg-blue-50 hover:text-blue-700">Back to Fund Requests</a>
        </div>
        <form method="POST" action="{{ route('disbursements.store') }}" class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            @csrf
            <header class="border-b border-slate-200 px-4 py-4 sm:px-6">
                <h2 class="text-lg font-semibold text-slate-950">Request Information</h2>
                <p class="mt-1 text-sm text-slate-500">Complete the required payment and recipient details.</p>
            </header>
            @include('disbursements.partials.fund-request-form-fields', [
                'formId' => 'create-fund-request',
                'values' => [],
                'useOld' => true,
                'locationDefaults' => $fundRequestLocationDefaults ?? [],
                'fundRequestProvinces' => $fundRequestProvinces ?? collect(),
            ])
            <footer class="flex flex-wrap justify-end gap-3 border-t border-slate-200 bg-slate-50/90 px-4 py-3 sm:px-6">
                <a href="{{ route('disbursements.index') }}" class="inline-flex h-10 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</a>
                <button type="submit" class="h-10 rounded-lg bg-blue-700 px-5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-200">Save Request</button>
            </footer>
        </form>
    </div>
    @include('disbursements.partials.fund-request-location-script')
@endsection

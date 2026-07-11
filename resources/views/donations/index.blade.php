@extends('layouts.app')

@section('title', 'Donations Portal')
@section('page-title', 'Donations Portal')

@section('content')
    @if ($errors->any())
        <x-alert type="error" class="mb-6">Please review the highlighted fields and try again.</x-alert>
    @endif

    <div class="space-y-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-slate-950">Donations Portal</h1>
                <p class="mt-1 text-sm text-slate-600">Log, review, and verify cash and in-kind contributions.</p>
            </div>
            <x-button type="button" class="sm:w-auto" data-dialog-open="log-contribution-dialog">
                Log New Contribution
            </x-button>
        </div>

        <x-donations.filter-bar :filters="$filters" :campaigns="$campaigns" />

        @if ($contributions->count())
            <x-donations.table :contributions="$contributions" />
            {{ $contributions->links() }}
        @else
            <div class="rounded-lg border border-slate-200 bg-white p-8">
                <x-dashboard.empty-state :message="request()->hasAny(['search', 'type', 'campaign_id', 'status'])
                    ? 'No contributions match the current filters.'
                    : 'No contributions have been logged yet.'" />
                <div class="mt-4 flex justify-center gap-3">
                    @if (request()->hasAny(['search', 'type', 'campaign_id', 'status']))
                        <a href="{{ route('donations.index') }}" class="text-sm font-semibold text-blue-700 hover:text-blue-800">Clear filters</a>
                    @endif
                    <button type="button" class="text-sm font-semibold text-blue-700 hover:text-blue-800" data-dialog-open="log-contribution-dialog">
                        Log New Contribution
                    </button>
                </div>
            </div>
        @endif
    </div>

    <x-donations.log-modal :donors="$donors" :campaigns="$campaigns" />
@endsection

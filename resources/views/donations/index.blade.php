@extends('layouts.app')

@section('title', 'Donations Portal')
@section('page-title', 'Donations Portal')
@section('page-subtitle', 'Log, review, and verify cash and in-kind contributions.')

@section('content')

    @if ($errors->any())
        <x-alert type="error" class="mb-6">
            Please review the highlighted fields and try again.
        </x-alert>
    @endif

    <div class="donations-page flex h-full min-h-0 flex-col gap-3 overflow-hidden">
            <x-donations.filter-bar
                :filters="$filters"
                :types="$availableDonationTypes"
                :donors="$filterDonors"
                :categories="$filterCategories"
            >
                @can('viewAny', \App\Models\Donor::class)
                    <a
                        href="{{ route('donors.index', ['from' => 'donations']) }}"
                        class="inline-flex h-10 w-full items-center justify-center gap-2 rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 transition hover:border-slate-400 hover:bg-slate-50 hover:text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2 sm:w-auto"
                    >
                        <x-icon name="users" class="h-4 w-4" />
                        View All Donors
                    </a>
                @endcan

                @can('create', \App\Models\Contribution::class)
                    <a href="{{ route('donations.create') }}" class="ui-button ui-button--primary inline-flex h-10 w-full items-center justify-center gap-2 px-4 shadow-sm sm:w-auto">
                        <x-icon name="plus" class="h-4 w-4" />
                        Add Donation
                    </a>
                @endcan
            </x-donations.filter-bar>

        @if ($contributions->count())
            <x-donations.table :contributions="$contributions" />
        @else
            <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="p-8">
                    <x-dashboard.empty-state
                        icon="cube"
                        :message="request()->hasAny(['search', 'type', 'date_from', 'date_to', 'donor_id', 'item', 'category'])
                            ? 'No contributions match the current filters.'
                            : 'No contributions have been logged yet.'"
                    />
                    <p class="mt-1 text-center text-sm text-slate-400">

                        @if (request()->hasAny(['search', 'type', 'date_from', 'date_to', 'donor_id', 'item', 'category']))

                            Try adjusting your search or filters.

                        @else

                            Start by logging a new cash or in-kind contribution.

                        @endif

                    </p>

                    <div class="mt-4 flex flex-wrap items-center justify-center gap-3">
                        @if (request()->hasAny(['search', 'type']))
                            <a
                                href="{{ route('donations.index') }}"
                                class="text-sm font-semibold text-blue-700 hover:text-blue-800"
                            >
                                Clear filters
                            </a>
                        @endif

                        @can('create', \App\Models\Contribution::class)
                            <a href="{{ route('donations.create') }}" class="ui-button ui-button--primary inline-flex w-auto items-center justify-center">
                                <x-icon
                                    name="plus"
                                    class="-ml-0.5 mr-1.5 h-4 w-4"
                                />
                                Add Donation
                            </a>
                        @endcan

                    </div>

                </div>

            </div>

        @endif

    </div>

@endsection

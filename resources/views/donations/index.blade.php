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

    <div class="space-y-4">
        <div class="space-y-3">
            <div class="flex flex-col gap-2 sm:flex-row sm:justify-end">
                @can('viewAny', \App\Models\Donor::class)
                    <x-button
                        type="button"
                        class="h-10 w-full gap-2 border border-blue-200 bg-white px-4 !text-blue-700 hover:border-blue-300 hover:bg-blue-50 hover:!text-blue-800 sm:w-auto"
                        data-dialog-open="donor-directory-modal"
                    >
                        <x-icon name="users" class="h-4 w-4" />
                        View All Donors
                    </x-button>
                @endcan

                @can('create', \App\Models\Contribution::class)
                    <x-button
                        type="button"
                        class="h-10 w-full gap-2 px-4 shadow-sm sm:w-auto"
                        data-dialog-open="log-contribution-dialog"
                    >
                        <x-icon name="plus" class="h-4 w-4" />
                        Add Donation
                    </x-button>
                @endcan
            </div>

            <x-donations.filter-bar :filters="$filters" />
        </div>

        @if ($contributions->count())
            <x-donations.table :contributions="$contributions" />
        @else
            <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-5 py-4">
                    <h2 class="text-base font-semibold text-slate-950">
                        Contributions
                    </h2>
                </div>

                <div class="p-8">
                    <x-dashboard.empty-state
                        icon="cube"
                        :message="request()->hasAny(['search', 'type', 'status'])
                            ? 'No contributions match the current filters.'
                            : 'No contributions have been logged yet.'"
                    />
                    <p class="mt-1 text-center text-sm text-slate-400">

                        @if (request()->hasAny(['search', 'type', 'status']))

                            Try adjusting your search or filters.

                        @else

                            Start by logging a new cash or in-kind contribution.

                        @endif

                    </p>

                    <div class="mt-4 flex flex-wrap items-center justify-center gap-3">
                        @if (request()->hasAny(['search', 'type', 'status']))
                            <a
                                href="{{ route('donations.index') }}"
                                class="text-sm font-semibold text-blue-700 hover:text-blue-800"
                            >
                                Clear filters
                            </a>
                        @endif

                        @can('create', \App\Models\Contribution::class)
                            <x-button
                                type="button"
                                class="w-auto"
                                data-dialog-open="log-contribution-dialog"
                            >
                                <x-icon
                                    name="plus"
                                    class="-ml-0.5 mr-1.5 h-4 w-4"
                                />
                                Add Donation
                            </x-button>
                        @endcan

                    </div>

                </div>

            </div>

        @endif

    </div>


    {{-- LOG CONTRIBUTION MODAL --}}
    @can('create', \App\Models\Contribution::class)
        <x-donations.log-modal
            :donors="$donors"
        />
    @endcan

    @can('viewAny', \App\Models\Donor::class)
        <x-donations.donor-directory-modal :donors="$donorDirectory" />
    @endcan

@endsection

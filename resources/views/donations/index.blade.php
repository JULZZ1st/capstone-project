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

    <div class="space-y-6">
        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">

            <x-dashboard.summary-card
                label="Total Contributions"
                :value="number_format((int) $summary->total_contributions)"
                description="All time."
                icon="gift"
                tone="neutral"
            />

            <x-dashboard.summary-card
                label="Cash Contributions"
                :value="number_format((int) $summary->cash_contributions)"
                description="All time."
                icon="wallet"
                tone="positive"
            />

            <x-dashboard.summary-card
                label="In-Kind Contributions"
                :value="number_format((int) $summary->in_kind_contributions)"
                description="All time."
                icon="cube"
                tone="purple"
            />

            <x-dashboard.summary-card
                label="Pending Verification"
                :value="number_format((int) $summary->pending_verification)"
                description="Awaiting review."
                icon="clock"
                tone="warning"
            />

        </section>
        <div class="flex flex-col gap-3 lg:flex-row lg:items-end">
            <div class="min-w-0 flex-1">
                <x-donations.filter-bar
                    :filters="$filters"
                    :campaigns="$campaigns"
                />
            </div>

            @can('create', \App\Models\Contribution::class)
                <div class="shrink-0">
                    <x-button
                        type="button"
                        class="w-full gap- shadow-sm lg:w-auto"
                        data-dialog-open="log-contribution-dialog"
                    >
                        <x-icon name="plus" class="h-4 w-4" />
                        Log New Contribution
                    </x-button>
                </div>
            @endcan

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
                        :message="request()->hasAny(['search', 'type', 'campaign_id', 'status'])
                            ? 'No contributions match the current filters.'
                            : 'No contributions have been logged yet.'"
                    />
                    <p class="mt-1 text-center text-sm text-slate-400">

                        @if (request()->hasAny(['search', 'type', 'campaign_id', 'status']))

                            Try adjusting your search or filters.

                        @else

                            Start by logging a new cash or in-kind contribution.

                        @endif

                    </p>

                    <div class="mt-4 flex flex-wrap items-center justify-center gap-3">
                        @if (request()->hasAny(['search', 'type', 'campaign_id', 'status']))
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
                                Log New Contribution
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
            :campaigns="$campaigns"
            :categories="$categories"
            :subcategories-by-category="$subcategoriesByCategory"
        />
    @endcan

@endsection

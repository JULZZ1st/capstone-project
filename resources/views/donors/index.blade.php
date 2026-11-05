@extends('layouts.app')

@section('title', 'Donor Management | '.config('dashboard.barangay_name'))
@section('page-title', 'Donor Management')
@section('page-subtitle', 'Search, add, and maintain donor information for barangay donation records.')

@section('content')
    @php
        $modal = old('_modal');
        $createModalOpen = ($errors->any() && $modal === 'create') || session('duplicate_donor_detected');
    @endphp

    <div class="space-y-4">
        @if ($errors->any())
            <x-alert type="error">
                Please review the highlighted donor profile fields and try again.
            </x-alert>
        @endif

        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <x-donors.search-bar :search="$search" :selected-type="$selectedType" />

            @can('create', \App\Models\Donor::class)
                <x-button type="button" class="h-10 w-full shrink-0 gap-2 px-4 sm:w-auto" data-dialog-open="create-donor-modal">
                    <x-icon name="plus" class="h-4 w-4" />
                    Add Donor Profile
                </x-button>
            @endcan
        </div>

        <div data-donor-results>
            <x-data-table caption="Registered donors" title="Donor directory" description="Contact information and locations for your registered donors." min-width="47rem" body-hook="data-donor-tbody">
                <x-slot:head>
                    <x-data-table.heading>Donor</x-data-table.heading>
                    <x-data-table.heading>Contact</x-data-table.heading>
                    <x-data-table.heading>Location</x-data-table.heading>
                    <x-data-table.heading>Status</x-data-table.heading>
                    <x-data-table.heading align="right">Actions</x-data-table.heading>
                </x-slot:head>
                @include('donors.partials.rows', ['donors' => $donors])
            </x-data-table>
            <div data-donor-pagination class="mt-3">{{ $donors->links() }}</div>

            <div data-donor-modals>
                @include('donors.partials.modals', ['donors' => $donors])
            </div>
        </div>
    </div>

    @can('create', \App\Models\Donor::class)
        <x-donors.create-modal :open="$createModalOpen" />
    @endcan
@endsection

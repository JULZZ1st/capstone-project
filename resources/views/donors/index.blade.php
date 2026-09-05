@extends('layouts.app')

@section('title', 'Donor Management | '.config('dashboard.barangay_name'))
@section('page-title', 'Donor Management')
@section('page-subtitle', 'Search, add, and maintain donor information for barangay donation records.')

@section('content')
    @php
        $modal = old('_modal');
        $createModalOpen = ($errors->any() && $modal === 'create') || session('duplicate_donor_detected');
    @endphp

    <div class="space-y-6">
        @if ($errors->any())
            <x-alert type="error">
                Please review the highlighted donor profile fields and try again.
            </x-alert>
        @endif

        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-end">
            <x-donors.search-bar :search="$search" :selected-type="$selectedType" />

            <x-button type="button" class="h-10 w-full shrink-0 gap-2 px-4 sm:w-auto" data-dialog-open="create-donor-modal">
                <x-icon name="plus" class="h-4 w-4" />
                Add Donor Profile
            </x-button>
        </div>

        @if ($donors->isEmpty())
            <x-dashboard.empty-state
                icon="inbox"
                class="min-h-72"
                :message="$search !== '' || $selectedType ? 'No donors match your search.' : 'No donors registered yet.'"
                :description="$search !== '' || $selectedType ? 'Try a different keyword or donor type.' : 'Add a donor profile to get started.'"
                :action="$search !== '' || $selectedType ? route('donors.index') : null"
                :action-label="$search !== '' || $selectedType ? 'Clear filters' : null"
            />
        @else
            <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($donors as $donor)
                    <div>
                        <x-donors.card :donor="$donor" />
                        <x-donors.edit-modal :donor="$donor" :open="$errors->any() && $modal === 'edit-'.$donor->id" />
                    </div>
                @endforeach
            </section>

            <div>
                {{ $donors->links() }}
            </div>
        @endif
    </div>

    <x-donors.create-modal :open="$createModalOpen" />
@endsection

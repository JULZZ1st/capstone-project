@extends('layouts.app')

@section('title', 'Donor Management | '.config('dashboard.barangay_name'))
@section('page-title', 'Donor Management')

@section('content')
    @php
        $modal = old('_modal');
        $createModalOpen = $errors->any() && $modal === 'create';
    @endphp

    <div class="space-y-6">
        @if ($errors->any())
            <x-alert type="error">
                Please review the highlighted donor profile fields and try again.
            </x-alert>
        @endif

        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h2 class="text-xl font-semibold text-slate-950">Donor Profiles</h2>
                <p class="mt-1 text-sm text-slate-600">Search, add, and maintain donor information for barangay donation records.</p>
            </div>
            <x-button type="button" class="w-auto" data-dialog-open="create-donor-modal">
                Add New Donor Profile
            </x-button>
        </div>

        <x-donors.search-bar :search="$search" :selected-type="$selectedType" />

        @if ($donors->isEmpty())
            <x-dashboard.empty-state :message="$search !== '' || $selectedType ? 'No donors match your search.' : 'No donors registered yet. Add a donor profile to get started.'" />
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

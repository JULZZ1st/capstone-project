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
            <div class="overflow-x-auto">
                <table class="min-w-[760px] w-full table-fixed text-left text-sm">
                    <caption class="sr-only">Registered donors</caption>
                    <colgroup>
                        <col class="w-[27%]"><col class="w-[24%]"><col class="w-[25%]"><col class="w-[12%]"><col class="w-[12%]">
                    </colgroup>
                    <thead class="sticky top-0 bg-slate-100 text-xs uppercase tracking-wide text-slate-500 shadow-[0_1px_0_0_rgba(226,232,240,1)]">
                        <tr>
                            <th scope="col" class="px-3 py-2.5 font-semibold">Donor</th>
                            <th scope="col" class="px-3 py-2.5 font-semibold">Contact</th>
                            <th scope="col" class="px-3 py-2.5 font-semibold">Location</th>
                            <th scope="col" class="px-3 py-2.5 font-semibold">Status</th>
                            <th scope="col" class="px-3 py-2.5 text-right font-semibold">Actions</th>
                        </tr>
                    </thead>
                </table>
                <div data-donor-scroll class="max-h-[calc(100vh-17rem)] overflow-y-auto overscroll-contain">
                    <table class="min-w-[760px] w-full table-fixed text-left text-sm">
                        <caption class="sr-only">Donor records</caption>
                        <colgroup>
                            <col class="w-[27%]"><col class="w-[24%]"><col class="w-[25%]"><col class="w-[12%]"><col class="w-[12%]">
                        </colgroup>
                        <tbody data-donor-tbody class="divide-y divide-slate-100">
                            @include('donors.partials.rows', ['donors' => $donors])
                        </tbody>
                    </table>
                </div>
            </div>

            <div data-donor-pagination class="mt-3">
                {{ $donors->links() }}
            </div>

            <div data-donor-modals>
                @include('donors.partials.modals', ['donors' => $donors])
            </div>
        </div>
    </div>

    @can('create', \App\Models\Donor::class)
        <x-donors.create-modal :open="$createModalOpen" />
    @endcan
@endsection

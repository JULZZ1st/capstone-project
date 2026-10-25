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

            @can('create', \App\Models\Donor::class)
                <x-button type="button" class="h-10 w-full shrink-0 gap-2 px-4 sm:w-auto" data-dialog-open="create-donor-modal">
                    <x-icon name="plus" class="h-4 w-4" />
                    Add Donor Profile
                </x-button>
            @endcan
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
            <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-[920px] w-full text-left text-sm">
                        <caption class="sr-only">Registered donors</caption>
                        <thead class="sticky top-0 bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th scope="col" class="px-4 py-3 font-semibold">Donor Name</th>
                                <th scope="col" class="px-4 py-3 font-semibold">Donor Type</th>
                                <th scope="col" class="px-4 py-3 font-semibold">Contact Number</th>
                                <th scope="col" class="px-4 py-3 font-semibold">Email</th>
                                <th scope="col" class="px-4 py-3 font-semibold">Barangay</th>
                                <th scope="col" class="px-4 py-3 font-semibold">City / Municipality</th>
                                <th scope="col" class="px-4 py-3 font-semibold">Status</th>
                                <th scope="col" class="px-4 py-3 text-right font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($donors as $donor)
                                <tr class="align-top hover:bg-slate-50/70">
                                    <td class="px-4 py-3">
                                        <div class="font-semibold text-slate-950">{{ $donor->full_name }}</div>
                                        <div class="mt-0.5 text-xs text-slate-500">{{ $donor->address }}</div>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-slate-700">{{ config('donors.types')[$donor->donor_type] ?? ucfirst($donor->donor_type) }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-slate-700">{{ $donor->contact_number ?: 'Not provided' }}</td>
                                    <td class="max-w-48 break-words px-4 py-3 text-slate-700">{{ $donor->email ?: 'Not provided' }}</td>
                                    <td class="px-4 py-3 text-slate-700">{{ $donor->barangay ?: $donor->barangay_area ?: 'Not provided' }}</td>
                                    <td class="px-4 py-3 text-slate-700">{{ $donor->city_municipality ?: 'Not provided' }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">{{ config('donors.statuses')[$donor->status] ?? ucfirst($donor->status) }}</span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <details class="relative flex justify-end">
                                            <summary class="flex h-9 w-9 cursor-pointer list-none items-center justify-center rounded-lg text-slate-600 transition hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-blue-500" aria-label="Actions for {{ $donor->full_name }}">
                                                <x-icon name="ellipsis-vertical" class="h-5 w-5" />
                                            </summary>
                                            <div class="absolute right-0 top-full z-20 mt-1 w-36 rounded-lg border border-slate-200 bg-white p-1 shadow-lg">
                                                @can('view', $donor)
                                                    <button type="button" class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-50" data-dialog-open="view-donor-modal-{{ $donor->id }}">
                                                        <x-icon name="eye" class="h-4 w-4" />
                                                        View
                                                    </button>
                                                @endcan
                                                @can('update', $donor)
                                                    <button type="button" class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-50" data-dialog-open="edit-donor-modal-{{ $donor->id }}">
                                                        <x-icon name="edit" class="h-4 w-4" />
                                                        Edit
                                                    </button>
                                                @endcan
                                                @can('delete', $donor)
                                                    <button type="button" class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-left text-sm text-red-700 hover:bg-red-50" data-dialog-open="remove-donor-modal-{{ $donor->id }}">
                                                        <x-icon name="trash" class="h-4 w-4" />
                                                        Delete
                                                    </button>
                                                @endcan
                                            </div>
                                        </details>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>

            @foreach ($donors as $donor)
                @can('view', $donor)
                    <x-donors.view-modal :donor="$donor" />
                @endcan
                @can('update', $donor)
                    <x-donors.edit-modal :donor="$donor" :open="$errors->any() && $modal === 'edit-'.$donor->id" />
                @endcan
                @can('delete', $donor)
                    <x-donors.remove-modal :donor="$donor" />
                @endcan
            @endforeach

            <div>
                {{ $donors->links() }}
            </div>
        @endif
    </div>

    @can('create', \App\Models\Donor::class)
        <x-donors.create-modal :open="$createModalOpen" :start-step="$createModalOpen ? $wizardStartStep : 0" />
    @endcan
@endsection

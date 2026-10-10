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
                        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
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
                                        <div class="flex justify-end gap-2 whitespace-nowrap">
                                            <a href="{{ route('donors.show', $donor) }}" class="rounded-md border border-slate-300 px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-white">View</a>
                                            @can('update', $donor)
                                                <button type="button" class="rounded-md bg-blue-700 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-blue-800" data-dialog-open="edit-donor-modal-{{ $donor->id }}">Edit</button>
                                            @endcan
                                            @can('delete', $donor)
                                                <form method="POST" action="{{ route('donors.destroy', $donor) }}" onsubmit="return confirm('Remove this donor? If they have donation history, the profile will be deactivated instead of permanently deleted.')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="rounded-md border border-red-300 px-2.5 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-50">Remove</button>
                                                </form>
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>

            @foreach ($donors as $donor)
                <x-donors.edit-modal :donor="$donor" :open="$errors->any() && $modal === 'edit-'.$donor->id" />
            @endforeach

            <div>
                {{ $donors->links() }}
            </div>
        @endif
    </div>

    @can('create', \App\Models\Donor::class)
        <x-donors.create-modal :open="$createModalOpen" />
    @endcan
@endsection

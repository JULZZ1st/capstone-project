@extends('layouts.app')

@section('title', $donor->full_name.' | Donor History')
@section('page-title', 'Donor History')

@section('content')
    <div class="space-y-6">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <a href="{{ route('donors.index') }}" class="text-sm font-semibold text-blue-700 hover:text-blue-800">Back to Donor Management</a>
                <h2 class="mt-2 text-2xl font-semibold text-slate-950">{{ $donor->full_name }}</h2>
                <p class="mt-1 text-sm text-slate-600">{{ $donor->address }}</p>
            </div>
            <button type="button" class="inline-flex items-center justify-center rounded-md bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-800" data-dialog-open="edit-donor-modal-{{ $donor->id }}">
                Edit Profile
            </button>
        </div>

        <x-donors.history-summary :donor="$donor" />

        <x-dashboard.panel title="Profile Details">
            <dl class="grid gap-4 text-sm md:grid-cols-2">
                <div>
                    <dt class="text-slate-500">Email</dt>
                    <dd class="font-medium text-slate-950">{{ $donor->email ?: 'Not provided' }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Contact Number</dt>
                    <dd class="font-medium text-slate-950">{{ $donor->contact_number }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Contact Person</dt>
                    <dd class="font-medium text-slate-950">{{ $donor->contact_person ?: 'Not provided' }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Preferred Contact</dt>
                    <dd class="font-medium text-slate-950">{{ $donor->preferred_contact_method ? config('donors.contact_methods')[$donor->preferred_contact_method] : 'Not provided' }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Barangay / Area</dt>
                    <dd class="font-medium text-slate-950">{{ $donor->barangay_area ?: 'Not provided' }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Notes</dt>
                    <dd class="font-medium text-slate-950">{{ $donor->notes ?: 'Not provided' }}</dd>
                </div>
            </dl>
        </x-dashboard.panel>

        <x-dashboard.panel title="Donation History">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                    <tr class="bg-slate-100">
                        <th class="p-2 text-left">Type</th>
                        <th class="p-2 text-left">Amount</th>
                        <th class="p-2 text-left">Notes</th>
                        <th class="p-2 text-left">Date</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($donations as $donation)
                        <tr class="border-t">
                            <td class="p-2">
                                {{ config("donations.types.{$donation->type}") ?? ucfirst($donation->type) }}
                                @if ($donation->voided_at)
                                    <span class="ml-1 inline-flex items-center rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-800 ring-1 ring-inset ring-red-200">Voided</span>
                                @endif
                            </td>
                            <td class="p-2">PHP {{ number_format((float) $donation->amount, 2) }}</td>
                            <td class="p-2">{{ $donation->notes ?: 'Not provided' }}</td>
                            <td class="p-2">{{ optional($donation->received_at)->toFormattedDateString() ?: 'Not provided' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="p-4 text-center text-slate-500">No donation history for this donor yet.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $donations->links() }}
            </div>
        </x-dashboard.panel>
    </div>

    <x-donors.edit-modal :donor="$donor" :open="$errors->any() && old('_modal') === 'edit-'.$donor->id" />
@endsection

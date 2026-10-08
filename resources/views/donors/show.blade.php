@extends('layouts.app')

@section('title', $donor->full_name.' | Donor History')
@section('page-title', 'Donor History')

@section('content')
    <div class="space-y-6">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <a href="{{ route('donors.index') }}" class="text-sm font-semibold text-blue-700 hover:text-blue-800">Back to Donor Management</a>
                <h2 class="mt-2 text-2xl font-semibold text-slate-950">{{ $donor->full_name }}</h2>
                <p class="mt-1 text-sm text-slate-600">{{ config('donors.types')[$donor->donor_type] ?? ucfirst($donor->donor_type) }}</p>
            </div>
            <div class="flex gap-2">
                <button type="button" class="inline-flex items-center justify-center rounded-md bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-800" data-dialog-open="edit-donor-modal-{{ $donor->id }}">
                    Edit Profile
                </button>
                <form method="POST" action="{{ route('donors.destroy', $donor) }}" onsubmit="return confirm('Remove this donor? If they have donation history, the profile will be deactivated instead of permanently deleted.')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="inline-flex items-center justify-center rounded-md border border-red-300 px-4 py-2.5 text-sm font-semibold text-red-700 hover:bg-red-50">
                        Remove
                    </button>
                </form>
            </div>
        </div>

        <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4">
                <h2 class="text-sm font-bold uppercase tracking-wide text-slate-950">Profile Information</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <tbody class="divide-y divide-slate-100">
                        <tr><th class="w-56 bg-slate-50 px-5 py-3 text-left font-medium text-slate-500">Donor Type</th><td class="px-5 py-3 font-medium text-slate-950">{{ config('donors.types')[$donor->donor_type] ?? ucfirst($donor->donor_type) }}</td></tr>
                        <tr><th class="bg-slate-50 px-5 py-3 text-left font-medium text-slate-500">Email</th><td class="px-5 py-3 font-medium text-slate-950">{{ $donor->email ?: 'Not provided' }}</td></tr>
                        <tr><th class="bg-slate-50 px-5 py-3 text-left font-medium text-slate-500">Contact Number</th><td class="px-5 py-3 font-medium text-slate-950">{{ $donor->contact_number ?: 'Not provided' }}</td></tr>
                        @if ($donor->contact_person)
                            <tr><th class="bg-slate-50 px-5 py-3 text-left font-medium text-slate-500">Contact Person</th><td class="px-5 py-3 font-medium text-slate-950">{{ $donor->contact_person }}</td></tr>
                        @endif
                        <tr><th class="bg-slate-50 px-5 py-3 text-left font-medium text-slate-500">Preferred Contact Method</th><td class="px-5 py-3 font-medium text-slate-950">{{ $donor->preferred_contact_method ? config('donors.contact_methods')[$donor->preferred_contact_method] : 'Not provided' }}</td></tr>
                        <tr><th class="bg-slate-50 px-5 py-3 text-left font-medium text-slate-500">Street / Address</th><td class="px-5 py-3 font-medium text-slate-950">{{ $donor->address ?: 'Not provided' }}</td></tr>
                        <tr><th class="bg-slate-50 px-5 py-3 text-left font-medium text-slate-500">Barangay</th><td class="px-5 py-3 font-medium text-slate-950">{{ $donor->barangay ?: $donor->barangay_area ?: 'Not provided' }}</td></tr>
                        <tr><th class="bg-slate-50 px-5 py-3 text-left font-medium text-slate-500">City / Municipality</th><td class="px-5 py-3 font-medium text-slate-950">{{ $donor->city_municipality ?: 'Not provided' }}</td></tr>
                        <tr><th class="bg-slate-50 px-5 py-3 text-left font-medium text-slate-500">Province</th><td class="px-5 py-3 font-medium text-slate-950">{{ $donor->province ?: 'Not provided' }}</td></tr>
                        <tr><th class="bg-slate-50 px-5 py-3 text-left font-medium text-slate-500">Status</th><td class="px-5 py-3 font-medium text-slate-950">{{ config('donors.statuses')[$donor->status] ?? ucfirst($donor->status) }}</td></tr>
                        <tr><th class="bg-slate-50 px-5 py-3 text-left font-medium text-slate-500">Notes</th><td class="whitespace-pre-line px-5 py-3 font-medium text-slate-950">{{ $donor->notes ?: 'Not provided' }}</td></tr>
                    </tbody>
                </table>
            </div>
        </section>

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

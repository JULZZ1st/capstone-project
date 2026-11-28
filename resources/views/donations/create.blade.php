@extends('layouts.app')

@section('title', 'Add Donation | '.config('dashboard.barangay_name'))
@section('page-title', 'Add Donation')
@section('page-subtitle', 'Record a cash or in-kind contribution.')

@section('content')
    @php($selectedType = old('donation_type', 'cash'))

    <div class="mx-auto max-w-5xl space-y-4">
        <a href="{{ route('donations.index') }}" class="inline-flex h-10 items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50">
            <x-icon name="arrow-left" class="h-4 w-4" />
            Back to Donations Portal
        </a>

        @if ($errors->any())
            <x-alert type="error">Please review the highlighted fields and try again.</x-alert>
        @endif

        <form method="POST" action="{{ route('donations.store') }}" enctype="multipart/form-data" class="ui-card overflow-hidden" data-contribution-form>
            @csrf
            <div class="space-y-5 p-4 sm:p-6">
                <div class="grid gap-2 sm:grid-cols-2" role="tablist" aria-label="Contribution type">
                    @foreach (config('donations.types') as $value => $label)
                        @php($isCash = $value === 'cash')
                        <button type="button" role="tab" aria-selected="{{ $selectedType === $value ? 'true' : 'false' }}" class="group flex min-h-14 items-center gap-3 rounded-xl border px-4 py-2.5 text-left transition {{ $selectedType === $value ? 'border-blue-500 bg-gradient-to-r from-blue-600 to-indigo-600 text-white shadow-sm' : 'border-slate-200 bg-slate-50 text-slate-700 hover:border-blue-200 hover:bg-blue-50' }}" data-contribution-tab="{{ $value }}">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg {{ $selectedType === $value ? 'bg-white/15 text-white' : 'bg-white text-slate-600 shadow-sm' }}"><x-icon :name="$isCash ? 'banknotes' : 'cube'" class="h-5 w-5" /></span>
                            <span class="min-w-0"><span class="block text-sm font-semibold">{{ $isCash ? 'Cash Donation' : 'In-Kind Goods' }}</span><span class="block truncate text-xs {{ $selectedType === $value ? 'text-blue-100' : 'text-slate-500' }}">{{ $isCash ? 'Record monetary contributions' : 'Record donated goods and items' }}</span></span>
                        </button>
                    @endforeach
                </div>

                <input id="donation_type" type="hidden" name="donation_type" value="{{ $selectedType }}" data-contribution-type>
                <x-form.error name="donation_type" />
                <x-donations.cash-fields :active="$selectedType === 'cash'" :donors="$donors" />
                <x-donations.in-kind-fields :active="$selectedType === 'in_kind'" :donors="$donors" />

                <div>
                    <x-form.label for="photo">Donation Photo (Optional)</x-form.label>
                    <input id="photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp" class="mt-1 block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm file:mr-4 file:rounded-md file:border-0 file:bg-blue-50 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-blue-700 hover:file:bg-blue-100">
                    <p class="mt-1 text-xs text-slate-500">Upload one JPG, PNG, or WEBP image up to 5 MB.</p>
                    <x-form.error name="photo" />
                </div>
            </div>

            <div class="flex flex-col-reverse gap-3 border-t border-slate-200 bg-slate-50 px-4 py-4 sm:flex-row sm:justify-end sm:px-6">
                <a href="{{ route('donations.index') }}" class="inline-flex h-10 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</a>
                <x-button class="sm:w-auto" data-contribution-submit><span data-contribution-submit-label>Save Contribution</span></x-button>
            </div>
        </form>
    </div>
@endsection

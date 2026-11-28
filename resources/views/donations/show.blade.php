@extends('layouts.app')

@section('title', 'Contribution Details')
@section('page-title', 'Contribution Details')
@section('page-subtitle', 'Review contribution information and supporting documents.')

@section('content')
    @if ($errors->any() || $errors->deedUpload->any())
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert" tabindex="-1" data-validation-summary>
            <p class="font-semibold">The donation could not be updated. Please review these requirements:</p>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                @foreach (array_unique(array_merge($errors->all(), $errors->deedUpload->all())) as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <x-donations.contribution-details-page :contribution="$contribution" />
@endsection

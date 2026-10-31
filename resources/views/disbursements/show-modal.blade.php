@extends('layouts.app')

@section('title', 'Fund Request Details')
@section('page-title', 'Fund Requests')
@section('page-subtitle', 'Review request details before approval or rejection.')

@section('content')
    @include('disbursements.partials.details-modal', ['disbursement' => $disbursement, 'modalId' => 'fund-request-details-modal', 'directPage' => true])
@endsection

@push('scripts')
    @include('disbursements.partials.fund-request-location-script')
    @include('disbursements.partials.details-modal-script')
@endpush

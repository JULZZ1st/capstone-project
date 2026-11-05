@props(['disbursement'])

@php
    $statusLabel = $disbursement->isVoided()
        ? 'Cancelled'
        : ($disbursement->status === 'pending'
            ? 'Pending Approval'
            : ($disbursement->status === 'approved' ? 'Approved Fund Request' : ucfirst($disbursement->status)));
@endphp

<tr class="align-top hover:bg-slate-50/70" data-fund-request-id="{{ $disbursement->id }}" data-requested-date="{{ ($disbursement->requested_date ?? $disbursement->created_at)->format('Y-m-d') }}" data-amount="{{ $disbursement->amount }}" data-recipient="{{ $disbursement->recipient ?? '' }}" data-payee-address="{{ $disbursement->payee_address ?? '' }}" data-payee-tin="{{ $disbursement->payee_tin ?? '' }}" data-fund-source="{{ $disbursement->fund_source ?? '' }}" data-obr-number="{{ $disbursement->obr_number ?? '' }}" data-purpose="{{ $disbursement->purpose }}" data-notes="{{ $disbursement->notes ?? '' }}" data-status="{{ $disbursement->status }}" data-approval-url="{{ route('disbursements.approve', $disbursement) }}" data-rejection-url="{{ route('disbursements.reject', $disbursement) }}" data-can-approve="{{ auth()->user()->can('approve', $disbursement) ? 'true' : 'false' }}" data-treasurer-certified="{{ $disbursement->hasTreasurerCertification() ? 'true' : 'false' }}">
    <td>
        <span class="whitespace-nowrap">{{ ($disbursement->requested_date ?? $disbursement->created_at)->format('M d, Y') }}</span>
        <span class="data-table__secondary max-w-40">By {{ $disbursement->requester?->name ?? 'System' }}</span>
    </td>
    <td><span class="data-table__primary">{{ $disbursement->recipient ?? 'Not provided' }}</span></td>
    <td>
        <span class="data-table__primary">{{ $disbursement->purpose }}</span>
        @if ($disbursement->notes)
            <span class="data-table__secondary max-w-64">{{ $disbursement->notes }}</span>
        @endif
        @if ($disbursement->isVoided())
            <span class="data-table__secondary max-w-64 !text-red-700">Cancellation Reason: {{ $disbursement->void_reason }}</span>
        @endif
    </td>
    <td class="data-table__number font-semibold text-slate-950">PHP {{ number_format((float) $disbursement->amount, 2) }}</td>
    <td>
        <span class="inline-flex max-w-40 rounded-lg bg-slate-100 px-2.5 py-1 text-xs font-semibold leading-5 text-slate-700">{{ $statusLabel }}</span>
        <span class="sr-only">{{ $disbursement->status }}</span>
    </td>
    <td>
        <div class="data-table__actions" data-fund-request-action>
            <x-data-table.action :label="'View fund request '.$disbursement->id" data-open-fund-request-details="fund-request-details-modal-{{ $disbursement->id }}" data-fund-request-action data-testid="view-fund-request-{{ $disbursement->id }}">View Details</x-data-table.action>
            @can('update', $disbursement)
                <x-data-table.action :label="'Edit fund request '.$disbursement->id" icon="pencil" data-open-fund-request-edit="fund-request-details-modal-{{ $disbursement->id }}-edit" data-fund-request-action />
            @endcan
        </div>
    </td>
</tr>

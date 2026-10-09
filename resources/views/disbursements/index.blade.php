@extends('layouts.app')

@section('title', 'Fund Requests')
@section('page-title', 'Fund Requests')
@section('page-subtitle', 'Submit and review cash requests with maker-checker approval.')

@section('content')
    <div class="space-y-6">

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <div>
                <h2 class="text-base font-semibold text-slate-950">Create Fund Request</h2>
                <p class="mt-1 text-sm text-slate-600">Submit a cash fund request for maker-checker approval.</p>
            </div>
            <form method="POST" action="{{ route('disbursements.store') }}" class="mt-5 space-y-5">
                @csrf
                <fieldset>
                    <legend class="text-xs font-semibold uppercase tracking-wide text-slate-500">Request Details</legend>
                    <div class="mt-3 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                        <x-form.input name="amount" label="Amount" type="number" step="0.01" min="0.01" required />
                        <x-form.input name="recipient" label="Recipient / Payee" required />
                        <x-form.input name="requested_date" label="Requested Date" type="date" required />
                        <div class="md:col-span-2 lg:col-span-3">
                            <x-form.label for="purpose">Purpose</x-form.label>
                            <textarea id="purpose" name="purpose" rows="2" required class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">{{ old('purpose') }}</textarea>
                            <x-form.error name="purpose" />
                        </div>
                    </div>
                </fieldset>

                <fieldset class="border-t border-slate-100 pt-5">
                    <legend class="text-xs font-semibold uppercase tracking-wide text-slate-500">Additional Information</legend>
                    <div class="mt-3 grid gap-4 md:grid-cols-2">
                        <div>
                            <x-form.label for="supporting_reference">Supporting Reference (Optional)</x-form.label>
                            <input id="supporting_reference" name="supporting_reference" type="text" value="{{ old('supporting_reference') }}" class="mt-1 block w-full rounded-md border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <p class="mt-1 text-xs text-slate-500">e.g. Purchase Request No., Receipt No., Check No., or supporting document reference</p>
                            <x-form.error name="supporting_reference" />
                        </div>
                        <div>
                            <x-form.label for="notes">Notes / Remarks (Optional)</x-form.label>
                            <textarea id="notes" name="notes" rows="2" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">{{ old('notes') }}</textarea>
                            <x-form.error name="notes" />
                        </div>
                    </div>
                </fieldset>

                <div class="flex justify-end border-t border-slate-100 pt-4">
                    <x-button class="w-full sm:w-auto">Submit Request</x-button>
                </div>
            </form>
        </section>

        <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-5 py-4">
                <h2 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Request history</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-5 py-3">Date</th>
                                <th class="px-5 py-3">Recipient / Payee</th>
                                <th class="px-5 py-3">Purpose</th>
                                <th class="px-5 py-3 text-right">Amount</th>
                                <th class="px-5 py-3">Requested By</th>
                                <th class="px-5 py-3">Status</th>
                                <th class="px-5 py-3 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @forelse ($disbursements as $disbursement)
                                @php
                                    $statusLabel = $disbursement->isVoided()
                                        ? 'Cancelled'
                                        : ($disbursement->status === 'pending'
                                            ? 'Pending Approval'
                                            : ($disbursement->status === 'approved' ? 'Approved Fund Request' : ucfirst($disbursement->status)));
                                @endphp
                                <tr>
                                    <td class="whitespace-nowrap px-5 py-4 text-slate-600">{{ ($disbursement->requested_date ?? $disbursement->created_at)->format('M d, Y') }}</td>
                                    <td class="px-5 py-4 text-slate-700">{{ $disbursement->recipient ?? 'Not provided' }}</td>
                                    <td class="px-5 py-4 text-slate-700">
                                        <p>{{ $disbursement->purpose }}</p>
                                        @if ($disbursement->supporting_reference)
                                            <p class="mt-1 text-xs text-slate-500">Ref: {{ $disbursement->supporting_reference }}</p>
                                        @endif
                                        @if ($disbursement->notes)
                                            <p class="mt-1 text-xs text-slate-500">{{ $disbursement->notes }}</p>
                                        @endif
                                        @if ($disbursement->isVoided())
                                            <p class="mt-1 text-xs font-semibold text-red-700">Cancellation Reason: {{ $disbursement->void_reason }}</p>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-4 text-right font-semibold text-slate-950">PHP {{ number_format((float) $disbursement->amount, 2) }}</td>
                                    <td class="whitespace-nowrap px-5 py-4 text-slate-600">{{ $disbursement->requester->name }}</td>
                                    <td class="px-5 py-4">
                                        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">{{ $statusLabel }}</span>
                                        <span class="sr-only">{{ $disbursement->status }}</span>
                                    </td>
                                    <td class="px-5 py-4">
                                        <div class="flex items-center justify-end gap-3">
                                            <a href="{{ route('disbursements.show', $disbursement) }}" class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">View</a>
                                            @can('void', $disbursement)
                                                <details class="relative">
                                                    <summary class="cursor-pointer rounded-md bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700">Cancel</summary>
                                                    <form method="POST" action="{{ route('disbursements.void', $disbursement) }}" class="absolute right-0 z-10 mt-2 w-72 space-y-2 rounded border border-slate-100 bg-white p-3 shadow-lg">
                                                        @csrf @method('PATCH')
                                                        <x-form.label for="void_reason_{{ $disbursement->id }}">Cancellation Reason</x-form.label>
                                                        <textarea id="void_reason_{{ $disbursement->id }}" name="void_reason" rows="2" required class="block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-red-500 focus:ring-red-500"></textarea>
                                                        <x-button class="w-full">Confirm Cancellation</x-button>
                                                    </form>
                                                </details>
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-5 py-10 text-center text-sm text-slate-500">No fund requests have been submitted.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
        </section>
    </div>
@endsection

@extends('layouts.app')

@section('title', 'Contribution Details')
@section('page-title', 'Contribution Details')

@section('content')
    <div class="space-y-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <a href="{{ route('donations.index') }}" class="text-sm font-semibold text-blue-700 hover:text-blue-800">Back to donations</a>
                <h1 class="mt-2 text-2xl font-semibold text-slate-950">{{ $contribution->donor->full_name }}</h1>
                <p class="mt-1 text-sm text-slate-600">{{ config("donations.types.{$contribution->type}") }} contribution</p>
            </div>
            <x-donations.status-badge :status="$contribution->status" />
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <section class="rounded-lg border border-slate-200 bg-white p-5 lg:col-span-2">
                <h2 class="text-base font-semibold text-slate-950">Record</h2>
                <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-semibold uppercase text-slate-500">Donation ID (system-generated)</dt>
                        <dd class="mt-1 text-sm font-semibold text-slate-900">{{ $contribution->receipt_number ?? '#'.str_pad((string) $contribution->id, 5, '0', STR_PAD_LEFT) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase text-slate-500">Date received</dt>
                        <dd class="mt-1 text-sm text-slate-900">{{ optional($contribution->received_at)->format('M d, Y h:i A') ?? 'Not provided' }}</dd>
                    </div>
                    @if ($contribution->isCash())
                        <div>
                            <dt class="text-xs font-semibold uppercase text-slate-500">Donation purpose</dt>
                            <dd class="mt-1 text-sm text-slate-900">{{ $contribution->purpose_type === 'specific' ? ($contribution->purpose_name ?: 'For a Specific Purpose') : 'General Barangay Use' }}</dd>
                        </div>
                    @endif
                    <div>
                        <dt class="text-xs font-semibold uppercase text-slate-500">Logged by</dt>
                        <dd class="mt-1 text-sm text-slate-900">{{ $contribution->creator?->name ?? trim(($contribution->creator?->first_name ?? '').' '.($contribution->creator?->last_name ?? '')) ?: 'System' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase text-slate-500">Official Receipt Number</dt>
                        <dd class="mt-1 text-sm text-slate-900">{{ $contribution->official_receipt_number ?? 'Not provided' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase text-slate-500">Bank Reference Number</dt>
                        <dd class="mt-1 text-sm text-slate-900">{{ $contribution->reference_number ?? 'Not provided' }}</dd>
                    </div>
                </dl>

                @if ($contribution->photo_path)
                    <div class="mt-6">
                        <h3 class="text-sm font-semibold text-slate-900">Donation Photo</h3>
                        <img src="{{ route('donations.photo', $contribution) }}" alt="Photo attached to contribution {{ $contribution->receipt_number }}" class="mt-2 max-h-96 rounded-lg border border-slate-200 object-contain">
                    </div>
                @endif

                @if ($contribution->isCash())
                    <div class="mt-6 rounded-md bg-slate-50 p-4">
                        <p class="text-sm font-semibold text-slate-600">Amount</p>
                        <p class="mt-1 text-2xl font-bold text-slate-950">PHP {{ number_format((float) $contribution->amount, 2) }}</p>
                        <p class="mt-1 text-sm text-slate-500">{{ $contribution->paymentMethodLabel() }}</p>
                        @if ($contribution->payment_method === 'check')
                            <p class="mt-2 text-sm text-slate-600">Check {{ $contribution->check_number ?? '—' }} · {{ $contribution->bank_name ?? 'Bank not provided' }}</p>
                        @elseif ($contribution->payment_method === 'bank_transfer')
                            <p class="mt-2 text-sm text-slate-600">Account: {{ $contribution->registered_bank_account ?? 'Not provided' }}</p>
                        @endif
                    </div>

                    @if ($contribution->document_review_status === 'missing_deed_admin_review')
                        <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                            <p class="font-semibold">Missing Deed – Admin Review</p>
                            <p class="mt-1">The donation was saved as Pending because the donor declined to sign the Deed of Donation.</p>
                            <p class="mt-2"><span class="font-semibold">Staff explanation:</span> {{ $contribution->deed_decline_reason }}</p>
                        </div>
                    @endif

                    @if ($contribution->documents_requested_at)
                        <div class="mt-4 rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900">
                            <p class="font-semibold">Documents requested by Admin</p>
                            <p class="mt-1">{{ $contribution->document_request_reason }}</p>
                        </div>
                    @endif
                @else
                    <div class="mt-6 overflow-hidden rounded-lg border border-slate-200">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                                <tr>
                                    <th class="px-4 py-3">Item</th>
                                    <th class="px-4 py-3">Quantity</th>
                                    <th class="px-4 py-3">Condition</th>
                                    <th class="px-4 py-3 text-right">Est. Total Value (PHP)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 bg-white">
                                @foreach ($contribution->items as $item)
                                    <tr>
                                        <td class="px-4 py-3">
                                            <p class="font-medium text-slate-900">{{ $item->name }}</p>
                                            @php($subcategoryLabel = $item->subcategory ? $subcategoryLabels->get($item->category.':'.$item->subcategory) : null)
                                            <p class="text-xs text-slate-500">
                                                {{ $categoryLabels->get($item->category, $item->category) }}@if($subcategoryLabel) &middot; {{ $subcategoryLabel }} @endif
                                                @if($item->category === 'others' && $item->category_description) &mdash; {{ $item->category_description }} @endif
                                            </p>
                                            @php($details = array_filter([
                                                $item->brand ? 'Brand: '.$item->brand : null,
                                                $item->model ? 'Model: '.$item->model : null,
                                                $item->serial_number ? 'SN: '.$item->serial_number : null,
                                                $item->expiration_date ? 'Expires: '.$item->expiration_date->format('M d, Y') : null,
                                            ]))
                                            @if($details)
                                                <p class="text-xs text-slate-400">{{ implode(' · ', $details) }}</p>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3">{{ $item->quantity }} {{ config("donations.units.{$item->unit}") }}</td>
                                        <td class="px-4 py-3">{{ config("donations.item_conditions.{$item->condition}") }}</td>
                                        <td class="px-4 py-3 text-right">{{ $item->estimated_value ? 'PHP '.number_format((float) $item->estimated_value, 2) : 'Not provided' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                @if ($contribution->notes)
                    <div class="mt-6">
                        <h3 class="text-sm font-semibold text-slate-900">Administrative notes</h3>
                        <p class="mt-2 whitespace-pre-line text-sm text-slate-600">{{ $contribution->notes }}</p>
                    </div>
                @endif

                @if ($contribution->evidence->isNotEmpty())
                    <div class="mt-6 border-t border-slate-200 pt-5">
                        <h3 class="text-sm font-semibold text-slate-900">Documents</h3>
                        <div class="mt-3 grid gap-2 sm:grid-cols-2">
                            @foreach ($contribution->evidence as $evidence)
                                <a href="{{ route('donations.evidence', [$contribution, $evidence]) }}" target="_blank" rel="noopener" class="flex min-w-0 items-center gap-3 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5 transition hover:border-blue-300 hover:bg-blue-50">
                                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-md bg-white text-xs font-bold uppercase text-blue-700 ring-1 ring-slate-200">{{ pathinfo($evidence->original_name, PATHINFO_EXTENSION) }}</span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-sm font-medium text-slate-800">{{ str($evidence->document_type)->replace('_', ' ')->title() }} · {{ $evidence->original_name }}</span>
                                        <span class="block text-xs text-slate-500">{{ $evidence->size < 1048576 ? number_format(max(1, $evidence->size / 1024), 0).' KB' : number_format($evidence->size / 1048576, 1).' MB' }} · View or download</span>
                                    </span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
            </section>

            @if (auth()->user()->can('verify', $contribution) || auth()->user()->can('void', $contribution) || auth()->user()->can('update', $contribution))
            <aside class="rounded-lg border border-slate-200 bg-white p-5">
                <h2 class="text-base font-semibold text-slate-950">Update Record</h2>
                @can('verify', $contribution)
                    <form method="POST" action="{{ route('donations.verify', $contribution) }}" class="mt-4">
                        @csrf
                        @method('PATCH')
                        <x-button>Verify Donation</x-button>
                    </form>
                @endcan

                @can('requestDocuments', $contribution)
                    <div class="mt-5 border-t border-slate-200 pt-4">
                        <h3 class="text-sm font-semibold text-slate-900">Request Missing Documents</h3>
                        <p class="mt-1 text-xs text-slate-500">The donation remains Pending until the required written documents are complete.</p>
                        <form method="POST" action="{{ route('donations.request-documents', $contribution) }}" class="mt-3 space-y-2">
                            @csrf
                            @method('PATCH')
                            <label for="document_request_reason" class="sr-only">Reason for requesting documents</label>
                            <textarea id="document_request_reason" name="document_request_reason" rows="3" required placeholder="Tell Staff which document is missing and what to provide" class="block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
                            <x-button>Request Documents</x-button>
                        </form>
                    </div>
                @endcan

                @can('issueCertificate', $contribution)
                    <div class="mt-5 border-t border-slate-200 pt-4">
                        <h3 class="text-sm font-semibold text-slate-900">Certificate of Donation</h3>
                        <p class="mt-1 text-xs text-slate-500">Request status: Pending. Upload the issued certificate to complete the request.</p>
                        <form method="POST" action="{{ route('donations.certificate.issue', $contribution) }}" enctype="multipart/form-data" class="mt-3 space-y-2">
                            @csrf
                            @method('PATCH')
                            <input type="file" name="certificate_of_donation" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" required class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-blue-50 file:px-2.5 file:py-1.5 file:text-xs file:font-semibold file:text-blue-700">
                            <x-form.error name="certificate_of_donation" />
                            <x-button>Mark as Issued</x-button>
                        </form>
                    </div>
                @elseif ($contribution->certificate_requested)
                    <div class="mt-5 border-t border-slate-200 pt-4">
                        <h3 class="text-sm font-semibold text-slate-900">Certificate of Donation</h3>
                        <p class="mt-1 text-sm text-slate-600">{{ ucfirst($contribution->certificate_status) }}{{ $contribution->certificate_issued_at ? ' on '.$contribution->certificate_issued_at->format('M d, Y') : '' }}.</p>
                        @if ($contribution->donor_tin)
                            <p class="mt-1 text-xs text-slate-500">Donor TIN recorded for certificate processing.</p>
                        @endif
                    </div>
                @endif

                @can('update', $contribution)
                    @if (auth()->user()->hasRole('admin') && $contribution->status === 'pending')
                        <form method="POST" action="{{ route('donations.update', $contribution) }}" class="mt-4">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="status" value="rejected">
                            <button type="submit" class="inline-flex w-full items-center justify-center rounded-md border border-rose-200 px-4 py-2.5 text-sm font-semibold text-rose-700 hover:bg-rose-50">
                                Reject Donation
                            </button>
                        </form>
                    @endif
                @endcan

                @can('void', $contribution)
                    <form method="POST" action="{{ route('contributions.void', $contribution) }}" class="mt-4 space-y-2">
                        @csrf
                        @method('PATCH')
                        <x-form.label for="void_reason">Void reason</x-form.label>
                        <textarea id="void_reason" name="void_reason" rows="3" required class="block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-red-500 focus:ring-red-500"></textarea>
                        <x-button>Void Contribution</x-button>
                    </form>
                @endcan

                @can('update', $contribution)
                    <form method="POST" action="{{ route('donations.update', $contribution) }}" class="mt-4 space-y-4">
                        @csrf
                        @method('PATCH')
                        <div>
                            <x-form.label for="status">Status</x-form.label>
                            <select id="status" name="status" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                @foreach (config('donations.statuses') as $value => $status)
                                    @continue($value === 'verified' && $contribution->status !== 'verified')
                                    <option value="{{ $value }}" @selected(old('status', $contribution->status) === $value)>{{ $status['label'] }}</option>
                                @endforeach
                            </select>
                            <x-form.error name="status" />
                        </div>
                        <div>
                            <x-form.label for="notes">Notes</x-form.label>
                            <textarea id="notes" name="notes" rows="4" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">{{ old('notes', $contribution->notes) }}</textarea>
                            <x-form.error name="notes" />
                        </div>
                        <x-button>Save Update</x-button>
                    </form>
                @else
                    <p class="mt-4 text-sm text-slate-500">This contribution is not editable for your role or its current status.</p>
                @endcan
            </aside>
            @endif
        </div>
    </div>
@endsection

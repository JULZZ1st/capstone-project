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
                        <dt class="text-xs font-semibold uppercase text-slate-500">Date received</dt>
                        <dd class="mt-1 text-sm text-slate-900">{{ optional($contribution->received_at)->format('M d, Y h:i A') ?? 'Not provided' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase text-slate-500">Campaign</dt>
                        <dd class="mt-1 text-sm text-slate-900">{{ $contribution->campaign?->name ?? 'No campaign' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase text-slate-500">Logged by</dt>
                        <dd class="mt-1 text-sm text-slate-900">{{ $contribution->creator?->name ?? trim(($contribution->creator?->first_name ?? '').' '.($contribution->creator?->last_name ?? '')) ?: 'System' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase text-slate-500">Reference</dt>
                        <dd class="mt-1 text-sm text-slate-900">{{ $contribution->reference_number ?? 'Not provided' }}</dd>
                    </div>
                </dl>

                @if ($contribution->isCash())
                    <div class="mt-6 rounded-md bg-slate-50 p-4">
                        <p class="text-sm font-semibold text-slate-600">Amount</p>
                        <p class="mt-1 text-2xl font-bold text-slate-950">PHP {{ number_format((float) $contribution->amount, 2) }}</p>
                        <p class="mt-1 text-sm text-slate-500">{{ config("donations.payment_methods.{$contribution->payment_method}") }}</p>
                    </div>
                @else
                    <div class="mt-6 overflow-hidden rounded-lg border border-slate-200">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                                <tr>
                                    <th class="px-4 py-3">Item</th>
                                    <th class="px-4 py-3">Quantity</th>
                                    <th class="px-4 py-3">Condition</th>
                                    <th class="px-4 py-3 text-right">Estimated Value</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 bg-white">
                                @foreach ($contribution->items as $item)
                                    <tr>
                                        <td class="px-4 py-3">
                                            <p class="font-medium text-slate-900">{{ $item->name }}</p>
                                            <p class="text-xs text-slate-500">{{ config("donations.item_categories.{$item->category}") }}</p>
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
            </section>

            <aside class="rounded-lg border border-slate-200 bg-white p-5">
                <h2 class="text-base font-semibold text-slate-950">Update Record</h2>
                @can('verify', $contribution)
                    <form method="POST" action="{{ route('donations.verify', $contribution) }}" class="mt-4">
                        @csrf
                        @method('PATCH')
                        <x-button>Verify Cash Contribution</x-button>
                    </form>
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
        </div>
    </div>
@endsection

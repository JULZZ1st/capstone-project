@forelse ($donors as $donor)
    <tr class="align-top transition hover:bg-slate-50/70">
        <td class="px-3 py-2.5">
            <div class="truncate font-semibold text-slate-950" title="{{ $donor->full_name }}">{{ $donor->full_name }}</div>
            <div class="mt-0.5 text-xs text-slate-500">{{ config('donors.types')[$donor->donor_type] ?? ucfirst($donor->donor_type) }}</div>
        </td>
        <td class="px-3 py-2.5 text-slate-700">
            <div class="truncate" title="{{ $donor->contact_number ?: 'Not provided' }}">{{ $donor->contact_number ?: 'Not provided' }}</div>
            <div class="mt-0.5 truncate text-xs text-slate-500" title="{{ $donor->email ?: 'Not provided' }}">{{ $donor->email ?: 'Not provided' }}</div>
        </td>
        <td class="px-3 py-2.5 text-slate-700">
            <div class="truncate" title="{{ $donor->barangay ?: $donor->barangay_area ?: 'Not provided' }}">{{ $donor->barangay ?: $donor->barangay_area ?: 'Not provided' }}</div>
            <div class="mt-0.5 truncate text-xs text-slate-500" title="{{ $donor->city_municipality ?: 'Not provided' }}">{{ $donor->city_municipality ?: 'Not provided' }}</div>
        </td>
        <td class="px-3 py-2.5 whitespace-nowrap"><span class="inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-700">{{ config('donors.statuses')[$donor->status] ?? ucfirst($donor->status) }}</span></td>
        <td class="px-3 py-2.5 text-right">
            <details class="relative inline-block text-left">
                <summary class="flex h-8 w-8 list-none items-center justify-center rounded-lg text-slate-500 transition hover:bg-slate-100 hover:text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500" aria-label="Actions for {{ $donor->full_name }}">
                    <x-icon name="dots-vertical" class="h-4 w-4" />
                </summary>
                <div class="absolute right-0 z-20 mt-1 w-32 rounded-lg border border-slate-200 bg-white p-1 text-left shadow-lg ring-1 ring-black/5">
                    <a href="{{ route('donors.show', $donor) }}" class="block rounded-md px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">View</a>
                    @can('update', $donor)
                        <button type="button" class="block w-full rounded-md px-3 py-2 text-left text-xs font-semibold text-slate-700 hover:bg-slate-50" data-dialog-open="edit-donor-modal-{{ $donor->id }}">Edit</button>
                    @endcan
                    @can('delete', $donor)
                        <button type="button" class="block w-full rounded-md px-3 py-2 text-left text-xs font-semibold text-red-700 hover:bg-red-50" data-dialog-open="remove-donor-modal-{{ $donor->id }}">Remove</button>
                    @endcan
                </div>
            </details>
        </td>
    </tr>
@empty
    <tr>
        <td colspan="5" class="h-48 px-4 py-8 text-center">
            <div class="mx-auto max-w-sm">
                <div class="text-3xl text-slate-300" aria-hidden="true">âŒ•</div>
                <p class="mt-2 font-semibold text-slate-800">No donors found</p>
                <p class="mt-1 text-sm text-slate-500">Try another name, email, phone number, barangay, or donor type.</p>
            </div>
        </td>
    </tr>
@endforelse

@forelse ($donors as $donor)
        <tr class="align-top hover:bg-slate-50/70">
            <td class="px-4 py-3"><div class="truncate font-semibold text-slate-950" title="{{ $donor->full_name }}">{{ $donor->full_name }}</div><div class="mt-0.5 truncate text-xs text-slate-500" title="{{ $donor->address }}">{{ $donor->address }}</div></td>
            <td class="px-4 py-3 whitespace-nowrap text-slate-700">{{ config('donors.types')[$donor->donor_type] ?? ucfirst($donor->donor_type) }}</td>
            <td class="px-4 py-3 whitespace-nowrap text-slate-700">{{ $donor->contact_number ?: 'Not provided' }}</td>
            <td class="truncate px-4 py-3 text-slate-700" title="{{ $donor->email ?: 'Not provided' }}">{{ $donor->email ?: 'Not provided' }}</td>
            <td class="truncate px-4 py-3 text-slate-700" title="{{ $donor->barangay ?: $donor->barangay_area ?: 'Not provided' }}">{{ $donor->barangay ?: $donor->barangay_area ?: 'Not provided' }}</td>
            <td class="truncate px-4 py-3 text-slate-700" title="{{ $donor->city_municipality ?: 'Not provided' }}">{{ $donor->city_municipality ?: 'Not provided' }}</td>
            <td class="px-4 py-3 whitespace-nowrap"><span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">{{ config('donors.statuses')[$donor->status] ?? ucfirst($donor->status) }}</span></td>
            <td class="px-4 py-3"><div class="flex justify-end gap-2 whitespace-nowrap"><a href="{{ route('donors.show', $donor) }}" class="rounded-md border border-slate-300 px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-white">View</a>@can('update', $donor)<button type="button" class="rounded-md bg-blue-700 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-blue-800" data-dialog-open="edit-donor-modal-{{ $donor->id }}">Edit</button>@endcan @can('delete', $donor)<button type="button" class="rounded-md border border-red-300 px-2.5 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-50" data-dialog-open="remove-donor-modal-{{ $donor->id }}">Remove</button>@endcan</div></td>
        </tr>
@empty
    <tr><td colspan="8" class="h-56 px-4 py-8 text-center"><div class="mx-auto max-w-sm"><div class="text-3xl text-slate-300" aria-hidden="true">⌕</div><p class="mt-2 font-semibold text-slate-800">No donors found</p><p class="mt-1 text-sm text-slate-500">Try another name, email, phone number, barangay, or donor type.</p></div></td></tr>
@endforelse

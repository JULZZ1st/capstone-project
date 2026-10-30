@forelse ($donors as $donor)
        <tr class="align-top hover:bg-slate-50/70">
            <td class="px-4 py-3"><div class="truncate font-semibold text-slate-950" title="{{ $donor->full_name }}">{{ $donor->full_name }}</div><div class="mt-0.5 truncate text-xs text-slate-500" title="{{ $donor->address }}">{{ $donor->address }}</div></td>
            <td class="px-4 py-3 whitespace-nowrap text-slate-700">{{ config('donors.types')[$donor->donor_type] ?? ucfirst($donor->donor_type) }}</td>
            <td class="px-4 py-3 whitespace-nowrap text-slate-700">{{ $donor->contact_number ?: 'Not provided' }}</td>
            <td class="truncate px-4 py-3 text-slate-700" title="{{ $donor->email ?: 'Not provided' }}">{{ $donor->email ?: 'Not provided' }}</td>
            <td class="truncate px-4 py-3 text-slate-700" title="{{ $donor->barangay ?: $donor->barangay_area ?: 'Not provided' }}">{{ $donor->barangay ?: $donor->barangay_area ?: 'Not provided' }}</td>
            <td class="truncate px-4 py-3 text-slate-700" title="{{ $donor->city_municipality ?: 'Not provided' }}">{{ $donor->city_municipality ?: 'Not provided' }}</td>
            <td class="px-4 py-3 whitespace-nowrap"><span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">{{ config('donors.statuses')[$donor->status] ?? ucfirst($donor->status) }}</span></td>
            <td class="px-4 py-3">
                <details class="relative flex justify-end">
                    <summary class="flex h-9 w-9 cursor-pointer list-none items-center justify-center rounded-lg text-slate-600 transition hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-blue-500" aria-label="Actions for {{ $donor->full_name }}">
                        <x-icon name="ellipsis-vertical" class="h-5 w-5" />
                    </summary>
                    <div class="absolute right-0 top-full z-20 mt-1 w-36 rounded-lg border border-slate-200 bg-white p-1 shadow-lg">
                        @can('view', $donor)
                            <button type="button" class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-50" data-dialog-open="view-donor-modal-{{ $donor->id }}">
                                <x-icon name="eye" class="h-4 w-4" />
                                View
                            </button>
                        @endcan
                        @can('update', $donor)
                            <button type="button" class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-50" data-dialog-open="edit-donor-modal-{{ $donor->id }}">
                                <x-icon name="edit" class="h-4 w-4" />
                                Edit
                            </button>
                        @endcan
                        @can('delete', $donor)
                            <button type="button" class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-left text-sm text-red-700 hover:bg-red-50" data-dialog-open="remove-donor-modal-{{ $donor->id }}">
                                <x-icon name="trash" class="h-4 w-4" />
                                Delete
                            </button>
                        @endcan
                    </div>
                </details>
            </td>
        </tr>
@empty
    <tr><td colspan="8" class="h-56 px-4 py-8 text-center"><div class="mx-auto max-w-sm"><div class="text-3xl text-slate-300" aria-hidden="true">⌕</div><p class="mt-2 font-semibold text-slate-800">No donors found</p><p class="mt-1 text-sm text-slate-500">Try another name, email, phone number, barangay, or donor type.</p></div></td></tr>
@endforelse

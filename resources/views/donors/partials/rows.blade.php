@forelse ($donors as $donor)
    <tr>
        <td>
            <span class="data-table__primary">{{ $donor->full_name }}</span>
            <span class="data-table__secondary">{{ config('donors.types')[$donor->donor_type] ?? ucfirst($donor->donor_type) }}</span>
        </td>
        <td>
            <span class="whitespace-nowrap">{{ $donor->contact_number ?: 'Not provided' }}</span>
            <span class="data-table__secondary max-w-56">{{ $donor->email ?: 'No email provided' }}</span>
        </td>
        <td>
            <span class="block min-w-28 max-w-48 break-words">{{ $donor->barangay ?: $donor->barangay_area ?: 'Not provided' }}</span>
            <span class="data-table__secondary">{{ $donor->city_municipality ?: 'City not provided' }}</span>
        </td>
        <td><span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">{{ config('donors.statuses')[$donor->status] ?? ucfirst($donor->status) }}</span></td>
        <td>
            <div class="data-table__actions" role="group" aria-label="Actions for {{ $donor->full_name }}">
                @can('view', $donor)
                    <x-data-table.action :label="'View '.$donor->full_name" data-dialog-open="view-donor-modal-{{ $donor->id }}">View</x-data-table.action>
                @endcan
                @can('update', $donor)
                    <x-data-table.action :label="'Edit '.$donor->full_name" icon="pencil" data-dialog-open="edit-donor-modal-{{ $donor->id }}" />
                @endcan
                @can('delete', $donor)
                    <x-data-table.action :label="'Remove '.$donor->full_name" icon="trash" :danger="true" data-dialog-open="remove-donor-modal-{{ $donor->id }}" />
                @endcan
            </div>
        </td>
    </tr>
@empty
    <x-data-table.empty :columns="5" title="No donors found" description="Try another name, email, phone number, barangay, or donor type." />
@endforelse

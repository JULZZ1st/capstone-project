@foreach ($donors as $donor)
    @can('update', $donor)
        <x-donors.edit-modal :donor="$donor" :open="false" />
    @endcan
    @can('delete', $donor)
        <x-donors.remove-modal :donor="$donor" />
    @endcan
@endforeach

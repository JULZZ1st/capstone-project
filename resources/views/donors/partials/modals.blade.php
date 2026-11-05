@foreach ($donors as $donor)
    @can('view', $donor)
        <x-donors.view-modal :donor="$donor" />
    @endcan
    @can('update', $donor)
        <x-donors.edit-modal :donor="$donor" :open="$errors->any() && old('_modal') === 'edit-'.$donor->id" />
    @endcan
    @can('delete', $donor)
        <x-donors.remove-modal :donor="$donor" />
    @endcan
@endforeach

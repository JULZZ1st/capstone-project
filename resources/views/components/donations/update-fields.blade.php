@props(['contribution', 'prefix' => ''])

@php($restoreInput = request()->routeIs('donations.show') || old('_modal') === 'update-contribution-'.$contribution->id)
<input type="hidden" name="_modal" value="update-contribution-{{ $contribution->id }}">
<div>
    <x-form.label :for="$prefix.'status'">Status</x-form.label>
    <select id="{{ $prefix }}status" name="status" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
        @foreach ($contribution->editableStatuses() as $status)
            @continue($status === 'rejected' && ! auth()->user()->hasRole('admin'))
            <option value="{{ $status }}" @selected(($restoreInput ? old('status', $contribution->status) : $contribution->status) === $status)>{{ config("donations.statuses.{$status}.label") }}</option>
        @endforeach
    </select>
    @if($restoreInput)<x-form.error name="status" :id="$prefix.'status-error'" />@endif
</div>
<div>
    <x-form.label :for="$prefix.'notes'">Notes</x-form.label>
    <textarea id="{{ $prefix }}notes" name="notes" rows="4" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">{{ $restoreInput ? old('notes', $contribution->notes) : $contribution->notes }}</textarea>
    @if($restoreInput)<x-form.error name="notes" :id="$prefix.'notes-error'" />@endif
</div>

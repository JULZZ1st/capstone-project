@props(['donor' => null, 'mode' => 'create'])

@php
    $prefix = $mode === 'edit' && $donor ? 'edit_'.$donor->id.'_' : '';
@endphp

<div class="grid gap-4 sm:grid-cols-2">
    <div>
        <label for="{{ $prefix }}donor_type" class="block text-sm font-medium text-slate-700">Donor Type</label>
        <select id="{{ $prefix }}donor_type" name="donor_type" class="mt-1 block w-full rounded-md border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
            @foreach (config('donors.types') as $value => $label)
                <option value="{{ $value }}" @selected(old('donor_type', $donor->donor_type ?? config('donors.defaults.type')) === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <x-form.error name="donor_type" />
    </div>

    <div>
        <label for="{{ $prefix }}status" class="block text-sm font-medium text-slate-700">Status</label>
        <select id="{{ $prefix }}status" name="status" class="mt-1 block w-full rounded-md border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
            @foreach (config('donors.statuses') as $value => $label)
                <option value="{{ $value }}" @selected(old('status', $donor->status ?? config('donors.defaults.status')) === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <x-form.error name="status" />
    </div>
</div>

<x-form.input name="full_name" label="Donor Name" :value="$donor->full_name ?? null" required autocomplete="name" />

<div class="grid gap-4 sm:grid-cols-2">
    <x-form.input name="email" label="Email" type="email" :value="$donor->email ?? null" autocomplete="email" />
    <x-form.input name="contact_number" label="Contact Number" :value="$donor->contact_number ?? null" required autocomplete="tel" />
</div>

<div class="grid gap-4 sm:grid-cols-2">
    <x-form.input name="contact_person" label="Contact Person" :value="$donor->contact_person ?? null" />
    <div>
        <label for="{{ $prefix }}preferred_contact_method" class="block text-sm font-medium text-slate-700">Preferred Contact Method</label>
        <select id="{{ $prefix }}preferred_contact_method" name="preferred_contact_method" class="mt-1 block w-full rounded-md border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
            <option value="">Not specified</option>
            @foreach (config('donors.contact_methods') as $value => $label)
                <option value="{{ $value }}" @selected(old('preferred_contact_method', $donor->preferred_contact_method ?? null) === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <x-form.error name="preferred_contact_method" />
    </div>
</div>

<div>
    <label for="{{ $prefix }}address" class="block text-sm font-medium text-slate-700">Address</label>
    <textarea id="{{ $prefix }}address" name="address" rows="3" class="mt-1 block w-full rounded-md border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" required>{{ old('address', $donor->address ?? '') }}</textarea>
    <x-form.error name="address" />
</div>

<x-form.input name="barangay_area" label="Barangay / Area" :value="$donor->barangay_area ?? null" />

<div>
    <label for="{{ $prefix }}notes" class="block text-sm font-medium text-slate-700">Notes</label>
    <textarea id="{{ $prefix }}notes" name="notes" rows="3" class="mt-1 block w-full rounded-md border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">{{ old('notes', $donor->notes ?? '') }}</textarea>
    <x-form.error name="notes" />
</div>

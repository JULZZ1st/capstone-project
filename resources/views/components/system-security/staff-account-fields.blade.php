@props(['user' => null, 'formKey' => 'create'])

@php($restoreInput = old('_modal') === $formKey)
<input type="hidden" name="_modal" value="{{ $formKey }}">
<div class="space-y-2">
    @foreach (['first_name' => 'First name', 'last_name' => 'Last name', 'email' => 'Email'] as $field => $label)
        <x-form.input :name="$field" :id="'staff-'.$formKey.'-'.$field" :label="$label"
            :type="$field === 'email' ? 'email' : 'text'" :value="$user?->getAttribute($field)"
            :use-old="$restoreInput" required class="text-sm" />
    @endforeach
    <div>
        <x-form.label :for="'staff-'.$formKey.'-role'">Role</x-form.label>
        <select id="staff-{{ $formKey }}-role" name="role" required class="mt-1 w-full rounded border px-2 py-1 text-sm">
            <option value="staff">Staff</option>
        </select>
        @if($restoreInput)<x-form.error name="role" :id="'staff-'.$formKey.'-role-error'" />@endif
    </div>
    @if($user)
        <div>
            <x-form.label :for="'staff-'.$formKey.'-status'">Status</x-form.label>
            <select id="staff-{{ $formKey }}-status" name="status" class="mt-1 w-full rounded border px-2 py-1 text-sm">
                @foreach ([config('auth_defaults.user_status') => 'Active', 'deactivated' => 'Deactivated'] as $value => $label)
                    <option value="{{ $value }}" @selected(($restoreInput ? old('status', $user->status) : $user->status) === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @if($restoreInput)<x-form.error name="status" :id="'staff-'.$formKey.'-status-error'" />@endif
        </div>
    @else
        <x-form.input name="password" :id="'staff-'.$formKey.'-password'" label="Initial password" type="password" :use-old="$restoreInput" required />
        <x-form.input name="password_confirmation" :id="'staff-'.$formKey.'-password-confirmation'" label="Confirm password" type="password" :use-old="$restoreInput" required />
    @endif
    <div class="mt-2 flex justify-end">
        <button type="submit" class="rounded bg-emerald-600 px-3 py-1.5 text-xs font-bold text-white">{{ $user ? 'Save' : 'Create' }}</button>
    </div>
</div>

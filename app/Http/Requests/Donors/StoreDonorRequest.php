<?php

namespace App\Http\Requests\Donors;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreDonorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'donor_type' => ['required', Rule::in(array_keys(config('donors.types')))],
            'first_name' => ['required_if:donor_type,individual', 'nullable', 'string', 'max:255'],
            'last_name' => ['required_if:donor_type,individual', 'nullable', 'string', 'max:255'],
            'organization_name' => ['required_if:donor_type,organization', 'nullable', 'string', 'max:255'],
            'full_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'string', config('donors.email_rule'), 'max:255'],
            'contact_number' => ['required', 'string', 'max:30'],
            'address' => ['required', 'string', 'max:500'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'preferred_contact_method' => ['nullable', Rule::in(array_keys(config('donors.contact_methods')))],
            'status' => ['nullable', Rule::in(array_keys(config('donors.statuses')))],
            'barangay_area' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            '_confirm_duplicate' => ['nullable', 'boolean'],
        ];
    }

    public function donorData(): array
    {
        return collect($this->validated())
            ->except('organization_name', '_confirm_duplicate')
            ->all();
    }

    public function duplicateConfirmed(): bool
    {
        return $this->boolean('_confirm_duplicate');
    }

    protected function prepareForValidation(): void
    {
        $donorType = Str::lower(trim((string) $this->input('donor_type')));
        $firstName = $this->nullableTrim('first_name');
        $lastName = $this->nullableTrim('last_name');
        $organizationName = $this->nullableTrim('organization_name') ?? $this->nullableTrim('full_name');
        $fullName = $donorType === 'organization'
            ? $organizationName
            : trim(implode(' ', array_filter([$firstName, $lastName])));

        $this->merge([
            'donor_type' => $donorType,
            'first_name' => $donorType === 'individual' ? $firstName : null,
            'last_name' => $donorType === 'individual' ? $lastName : null,
            'organization_name' => $donorType === 'organization' ? $organizationName : null,
            'full_name' => $fullName,
            'email' => $this->nullableLower('email'),
            'contact_number' => trim((string) $this->input('contact_number')),
            'address' => trim((string) $this->input('address')),
            'contact_person' => $donorType === 'organization' ? $this->nullableTrim('contact_person') : null,
            'preferred_contact_method' => $this->nullableLower('preferred_contact_method'),
            'status' => Str::lower(trim((string) $this->input('status', config('donors.defaults.status')))),
            'barangay_area' => $this->nullableTrim('barangay_area'),
            'notes' => $this->nullableTrim('notes'),
        ]);
    }

    private function nullableTrim(string $key): ?string
    {
        $value = trim((string) $this->input($key));

        return $value === '' ? null : $value;
    }

    private function nullableLower(string $key): ?string
    {
        $value = $this->nullableTrim($key);

        return $value ? Str::lower($value) : null;
    }
}

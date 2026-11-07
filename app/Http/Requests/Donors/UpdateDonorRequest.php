<?php

namespace App\Http\Requests\Donors;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateDonorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('donor')) ?? false;
    }

    public function rules(): array
    {
        return [
            'donor_type' => ['required', Rule::in(array_keys(config('donors.types')))],
            'first_name' => ['required_if:donor_type,individual', 'nullable', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required_if:donor_type,individual', 'nullable', 'string', 'max:255'],
            'suffix' => ['nullable', 'string', 'max:30'],
            'organization_name' => ['required_if:donor_type,organization', 'nullable', 'string', 'max:255'],
            'full_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'string', config('donors.email_rule'), 'max:255'],
            'tin' => ['nullable', 'string', 'max:30'],
            'contact_number' => ['required', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:500'],
            'barangay' => ['nullable', 'string', 'max:255'],
            'city_municipality' => ['nullable', 'string', 'max:255'],
            'province' => ['nullable', 'string', 'max:255'],
            'region' => ['nullable', 'string', 'max:255'],
            'contact_person' => ['required_if:donor_type,organization', 'nullable', 'string', 'max:255'],
            'preferred_contact_method' => ['nullable', Rule::in(array_keys(config('donors.contact_methods')))],
            'status' => ['nullable', Rule::in(array_keys(config('donors.statuses')))],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function donorData(): array
    {
        return collect($this->validated())
            ->except('organization_name')
            ->all();
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'donor_type' => Str::lower(trim((string) $this->input('donor_type', config('donors.defaults.type')))),
            'first_name' => $this->nullableTrim('first_name'),
            'middle_name' => $this->nullableTrim('middle_name'),
            'last_name' => $this->nullableTrim('last_name'),
            'suffix' => $this->nullableTrim('suffix'),
            'organization_name' => $this->nullableTrim('organization_name') ?? $this->nullableTrim('full_name'),
            'full_name' => $this->normalizedFullName(),
            'email' => $this->nullableLower('email'),
            'tin' => $this->nullableTrim('tin'),
            'contact_number' => trim((string) $this->input('contact_number')),
            'address' => trim((string) $this->input('address')),
            'barangay' => $this->nullableTrim('barangay') ?? $this->nullableTrim('barangay_area'),
            'city_municipality' => $this->nullableTrim('city_municipality'),
            'province' => $this->nullableTrim('province'),
            'region' => $this->nullableTrim('region'),
            'contact_person' => $this->nullableTrim('contact_person'),
            'preferred_contact_method' => $this->nullableLower('preferred_contact_method'),
            'status' => Str::lower(trim((string) $this->input('status', config('donors.defaults.status')))),
            'notes' => $this->nullableTrim('notes'),
        ]);
    }

    private function normalizedFullName(): ?string
    {
        $type = Str::lower(trim((string) $this->input('donor_type')));

        if ($type === 'organization') {
            return $this->nullableTrim('organization_name') ?? $this->nullableTrim('full_name');
        }

        $name = trim(implode(' ', array_filter([
            $this->nullableTrim('first_name'),
            $this->nullableTrim('middle_name'),
            $this->nullableTrim('last_name'),
            $this->nullableTrim('suffix'),
        ])));

        return $name === '' ? null : $name;
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

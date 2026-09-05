<?php

namespace App\Http\Requests\Donors;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateDonorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'donor_type' => ['required', Rule::in(array_keys(config('donors.types')))],
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'string', config('donors.email_rule'), 'max:255'],
            'contact_number' => ['required', 'string', 'max:30'],
            'address' => ['required', 'string', 'max:500'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'preferred_contact_method' => ['nullable', Rule::in(array_keys(config('donors.contact_methods')))],
            'status' => ['nullable', Rule::in(array_keys(config('donors.statuses')))],
            'barangay_area' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function donorData(): array
    {
        return $this->validated();
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'donor_type' => Str::lower(trim((string) $this->input('donor_type', config('donors.defaults.type')))),
            'full_name' => trim((string) $this->input('full_name')),
            'email' => $this->nullableLower('email'),
            'contact_number' => trim((string) $this->input('contact_number')),
            'address' => trim((string) $this->input('address')),
            'contact_person' => $this->nullableTrim('contact_person'),
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

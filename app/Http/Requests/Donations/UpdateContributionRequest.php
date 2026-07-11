<?php

namespace App\Http\Requests\Donations;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateContributionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('contribution')) ?? false;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(array_keys(config('donations.statuses')))],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'notes' => $this->blankToNull('notes'),
        ]);
    }

    private function blankToNull(string $key): ?string
    {
        $value = trim((string) $this->input($key));

        return $value === '' ? null : $value;
    }
}

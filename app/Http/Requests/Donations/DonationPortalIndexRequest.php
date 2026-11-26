<?php

namespace App\Http\Requests\Donations;

use App\Http\Requests\Concerns\ReadsScalarInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DonationPortalIndexRequest extends FormRequest
{
    use ReadsScalarInput;

    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', \App\Models\Contribution::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', Rule::in(array_keys(config('donations.types')))],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'search' => $this->nullableTrim('search'),
            'type' => $this->nullableTrim('type'),
        ]);
    }

    private function nullableTrim(string $key): ?string
    {
        $value = trim((string) $this->scalarInput($key));

        return $value === '' ? null : $value;
    }
}

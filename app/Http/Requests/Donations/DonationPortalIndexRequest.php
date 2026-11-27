<?php

namespace App\Http\Requests\Donations;

use App\Http\Requests\Concerns\ReadsScalarInput;
use App\Support\Workspaces\WorkspaceValidationRules;
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
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'donor_id' => [
                'nullable',
                'integer',
                WorkspaceValidationRules::exists('donors'),
            ],
            'item' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', Rule::in(array_keys(config('donations.item_main_categories')))],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'search' => $this->nullableTrim('search'),
            'type' => $this->nullableTrim('type'),
            'date_from' => $this->nullableTrim('date_from'),
            'date_to' => $this->nullableTrim('date_to'),
            'donor_id' => $this->nullableTrim('donor_id'),
            'item' => $this->nullableTrim('item'),
            'category' => $this->nullableTrim('category'),
        ]);
    }

    private function nullableTrim(string $key): ?string
    {
        $value = trim((string) $this->scalarInput($key));

        return $value === '' ? null : $value;
    }
}

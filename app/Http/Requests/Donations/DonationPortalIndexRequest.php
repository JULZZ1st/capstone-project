<?php

namespace App\Http\Requests\Donations;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Support\Workspaces\WorkspaceValidationRules;

class DonationPortalIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', \App\Models\Contribution::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', Rule::in(array_keys(config('donations.types')))],
            'campaign_id' => ['nullable', 'integer', WorkspaceValidationRules::exists('campaigns')],
            'status' => ['nullable', Rule::in(array_keys(config('donations.statuses')))],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'search' => $this->nullableTrim('search'),
            'type' => $this->nullableTrim('type'),
            'campaign_id' => $this->nullableTrim('campaign_id'),
            'status' => $this->nullableTrim('status'),
        ]);
    }

    private function nullableTrim(string $key): ?string
    {
        $value = trim((string) $this->input($key));

        return $value === '' ? null : $value;
    }
}

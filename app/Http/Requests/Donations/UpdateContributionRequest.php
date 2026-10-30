<?php

namespace App\Http\Requests\Donations;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateContributionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $contribution = $this->route('contribution');

        if (! $user || ! $user->can('update', $contribution)) {
            return false;
        }

        // Staff may correct their own editable records, but verification and
        // rejection are independent review decisions reserved for Admins.
        return $user->hasRole('admin')
            || ! in_array($this->input('status'), ['verified', 'rejected'], true);
    }

    public function rules(): array
    {
        $allowedStatuses = array_keys(config('donations.statuses'));

        // A pending donation must use the dedicated verification endpoint so
        // the reviewer and verification timestamp are recorded. Existing
        // verified records may still be edited for administrative notes.
        if ($this->route('contribution')?->status !== 'verified') {
            $allowedStatuses = array_values(array_diff($allowedStatuses, ['verified']));
        }

        if ($this->route('contribution')?->ledgerEntry()->exists()) {
            $allowedStatuses = [$this->route('contribution')->status];
        }

        return [
            'status' => [
                'required',
                Rule::in($allowedStatuses),
            ],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('notes')) {
            $this->merge(['notes' => $this->blankToNull('notes')]);
        }
    }

    private function blankToNull(string $key): ?string
    {
        $value = trim((string) $this->input($key));

        return $value === '' ? null : $value;
    }
}

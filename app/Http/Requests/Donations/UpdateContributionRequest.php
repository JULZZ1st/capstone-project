<?php

namespace App\Http\Requests\Donations;

use App\Http\Requests\Concerns\ReadsScalarInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateContributionRequest extends FormRequest
{
    use ReadsScalarInput;

    public function authorize(): bool
    {
        $user = $this->user();
        $contribution = $this->route('contribution');

        if (! $user || ! $user->can('update', $contribution)) {
            return false;
        }

        // Staff may correct their own editable records, but verification and
        // rejection are independent review decisions reserved for Admins.
        if ($user->hasRole('admin') && in_array($this->input('status'), ['verified', 'rejected'], true)) {
            return (int) $contribution->created_by !== (int) $user->id;
        }

        return ! in_array($this->input('status'), ['verified', 'rejected'], true);
    }

    public function rules(): array
    {
        $allowedStatuses = $this->route('contribution')->editableStatuses();

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

    protected function getRedirectUrl(): string
    {
        return route('donations.show', $this->route('contribution'));
    }

    private function blankToNull(string $key): ?string
    {
        $value = trim((string) $this->scalarInput($key));

        return $value === '' ? null : $value;
    }
}

<?php

namespace App\Http\Requests\SystemSecurity;

use App\Http\Requests\Concerns\ReadsScalarInput;
use App\Models\SecurityActivityEvent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SystemSecurityIndexRequest extends FormRequest
{
    use ReadsScalarInput;

    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', SecurityActivityEvent::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'action' => ['nullable', Rule::exists('security_activity_events', 'action')],
            'search' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'action' => $this->nullableTrim('action'),
            'search' => $this->nullableTrim('search'),
        ]);
    }

    private function nullableTrim(string $key): ?string
    {
        $value = trim((string) $this->scalarInput($key));

        return $value === '' ? null : $value;
    }
}

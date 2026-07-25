<?php

namespace App\Http\Requests\Reports;

use App\Models\Contribution;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Contribution::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'tab' => ['nullable', Rule::in(['financial-ledgers', 'in-kind-audits', 'philanthropy-roster'])],
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
        ];
    }

    public function tab(): string
    {
        return $this->validated('tab') ?? 'financial-ledgers';
    }

    public function year(): int
    {
        return (int) ($this->validated('year') ?? now()->year);
    }

    public function month(): int
    {
        return (int) ($this->validated('month') ?? now()->month);
    }
}

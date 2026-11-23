<?php

namespace App\Http\Requests\Reports;

use App\Support\Authorization\ReportAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Support\Workspaces\WorkspaceValidationRules;

class ReportIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! $this->user()) {
            return false;
        }

        $routeTab = [
            'reports.financial' => 'financial', 'reports.financial-ledgers' => 'financial-ledgers',
            'reports.inventory' => 'inventory', 'reports.audit' => 'audit',
            'reports.reconciliation' => 'reconciliation', 'reports.in-kind-audits' => 'in-kind-audits',
            'reports.philanthropy-roster' => 'philanthropy-roster',
        ][$this->route()?->getName()] ?? null;

        $tab = $routeTab ?? $this->input('tab');
        return app(ReportAccess::class)->can($this->user(), (string) ($tab ?: app(ReportAccess::class)->allowedTabs($this->user())->first()));
    }

    public function rules(): array
    {
        return [
            'tab' => ['nullable', Rule::in(['financial', 'inventory', 'audit', 'financial-ledgers', 'in-kind-audits', 'philanthropy-roster', 'reconciliation'])],
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'search' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', Rule::in(['highest', 'lowest', 'most_contributions', 'latest'])],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'period_id' => ['nullable', 'integer', WorkspaceValidationRules::exists('bank_reconciliations')],
        ];
    }

    public function tab(): string
    {
        return $this->validated('tab') ?? (string) app(ReportAccess::class)->allowedTabs($this->user())->first();
    }

    public function year(): int
    {
        return (int) ($this->validated('year') ?? now()->year);
    }

    public function month(): int
    {
        return (int) ($this->validated('month') ?? now()->month);
    }

    public function search(): ?string
    {
        return $this->validated('search');
    }

    public function sort(): ?string
    {
        return $this->validated('sort');
    }

    public function from(): ?string
    {
        return $this->validated('from');
    }

    public function to(): ?string
    {
        return $this->validated('to');
    }

    public function periodId(): ?int { return $this->validated('period_id') ? (int) $this->validated('period_id') : null; }

}

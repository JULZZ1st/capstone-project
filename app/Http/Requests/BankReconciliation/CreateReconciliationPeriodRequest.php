<?php

namespace App\Http\Requests\BankReconciliation;

use App\Models\BankReconciliation;
use Illuminate\Foundation\Http\FormRequest;

class CreateReconciliationPeriodRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $month = $this->input('period_month');
        $year = $this->input('period_year');

        if ($month !== null && $year !== null && is_numeric($month) && is_numeric($year)) {
            $start = sprintf('%04d-%02d-01', (int) $year, (int) $month);
            $this->merge([
                'period_start' => $start,
                'period_end' => date('Y-m-t', strtotime($start)),
            ]);
        }
    }

    public function authorize(): bool
    {
        return $this->user()?->can('prepare', BankReconciliation::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'bank_account' => ['nullable', 'string', 'max:255'],
            'period_month' => ['nullable', 'integer', 'between:1,12'],
            'period_year' => ['nullable', 'integer', 'between:2000,2100'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'opening_balance' => ['required', 'numeric', 'min:0'],
            'opening_balance_explanation' => ['nullable', 'string', 'max:5000'],
            'bank_ending_balance' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($validator->errors()->hasAny(['bank_account', 'period_start', 'period_end', 'opening_balance'])) {
                return;
            }

            $bankAccount = $this->input('bank_account') ?: config('bank_reconciliation.bank_accounts.0');
            $hasConfirmedPrevious = BankReconciliation::query()
                ->whereIn('status', ['locked', 'approved_locked'])
                ->whereNotNull('bank_ending_balance')
                ->where(function ($query) use ($bankAccount) {
                    $query->where('bank_account', $bankAccount);
                    if ($bankAccount === config('bank_reconciliation.bank_accounts.0')) {
                        $query->orWhereNull('bank_account');
                    }
                })
                ->whereDate('period_end', '<', $this->input('period_start'))
                ->exists();

            $hasPreviousDraft = BankReconciliation::query()
                ->where('status', 'draft')
                ->where(function ($query) use ($bankAccount) {
                    $query->where('bank_account', $bankAccount);
                    if ($bankAccount === config('bank_reconciliation.bank_accounts.0')) {
                        $query->orWhereNull('bank_account');
                    }
                })
                ->whereDate('period_end', '<', $this->input('period_start'))
                ->exists();

            if ($hasPreviousDraft) {
                $validator->errors()->add('period_start', 'Complete and submit the previous Draft period before starting a new period for this account.');
                return;
            }

            if (! $hasConfirmedPrevious && ! filled($this->input('opening_balance_explanation'))) {
                $validator->errors()->add('opening_balance_explanation', 'Explain the source of the starting balance when no confirmed previous period exists.');
            }
        });
    }
}

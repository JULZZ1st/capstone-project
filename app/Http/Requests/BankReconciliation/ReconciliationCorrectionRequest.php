<?php

namespace App\Http\Requests\BankReconciliation;

use Illuminate\Foundation\Http\FormRequest;

class ReconciliationCorrectionRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->hasRole('admin') ?? false; }
    public function rules(): array { return ['reason' => ['required', 'string', 'max:5000']]; }
}

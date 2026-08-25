<?php

namespace App\Http\Requests\Disbursements;

use App\Models\CashDisbursement;
use Illuminate\Foundation\Http\FormRequest;

class RejectDisbursementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('reject', $this->route('disbursement')) ?? false;
    }

    public function rules(): array
    {
        return ['rejection_reason' => ['required', 'string', 'max:2000']];
    }
}
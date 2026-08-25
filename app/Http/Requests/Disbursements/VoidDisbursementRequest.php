<?php

namespace App\Http\Requests\Disbursements;

use Illuminate\Foundation\Http\FormRequest;

class VoidDisbursementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('void', $this->route('disbursement')) ?? false;
    }

    public function rules(): array
    {
        return ['void_reason' => ['required', 'string', 'max:2000']];
    }
}
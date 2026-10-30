<?php

namespace App\Http\Requests\Disbursements;

use Illuminate\Foundation\Http\FormRequest;

class RequestMissingSignedVoucherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('requestMissingSignedVoucher', $this->route('disbursement')) ?? false;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:2000'],
        ];
    }
}

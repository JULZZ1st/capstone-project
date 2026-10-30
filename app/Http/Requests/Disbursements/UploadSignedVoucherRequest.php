<?php

namespace App\Http\Requests\Disbursements;

use App\Models\CashDisbursement;
use Illuminate\Foundation\Http\FormRequest;

class UploadSignedVoucherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('uploadSignedVoucher', $this->route('disbursement')) ?? false;
    }

    public function rules(): array
    {
        return [
            'signed_voucher' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ];
    }
}

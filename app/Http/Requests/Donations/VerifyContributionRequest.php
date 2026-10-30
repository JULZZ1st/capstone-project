<?php

namespace App\Http\Requests\Donations;

use Illuminate\Foundation\Http\FormRequest;

class VerifyContributionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('verify', $this->route('contribution')) ?? false;
    }

    public function rules(): array
    {
        return [];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $contribution = $this->route('contribution');

            if ($contribution?->payment_method === 'bank_transfer'
                && ! $contribution->evidence()->whereIn('document_type', ['bank_transfer_proof', 'supporting_evidence'])->exists()) {
                $validator->errors()->add('bank_transfer_proof', 'Bank Deposit/Transfer Proof must be uploaded before this donation can be verified.');
            }

            if (! $contribution?->isCash()) {
                return;
            }

            if (blank($contribution->official_receipt_number)) {
                $validator->errors()->add('official_receipt_number', 'Official Receipt Number is required before verification.');
            }

            if (! $contribution->evidence()->where('document_type', 'official_receipt')->exists()) {
                $validator->errors()->add('official_receipt_document', 'Official Receipt upload is required before verification.');
            }

            if (! $contribution->evidence()->where('document_type', 'certificate_of_acceptance')->exists()) {
                $validator->errors()->add('certificate_of_acceptance', 'Certificate of Acceptance is required before verification.');
            }

            if ((float) $contribution->amount >= 5000
                && ! $contribution->evidence()->where('document_type', 'deed_of_donation')->exists()) {
                $validator->errors()->add('deed_of_donation', 'This cash donation cannot be verified while the required Deed of Donation is missing. Review the donor exception and obtain the written document before verification.');
            }
        });
    }
}

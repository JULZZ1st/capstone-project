<?php

namespace App\Http\Requests\Donations;

use Illuminate\Foundation\Http\FormRequest;

class RequestContributionDocumentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('requestDocuments', $this->route('contribution')) ?? false;
    }

    public function rules(): array
    {
        return [
            'document_request_reason' => ['required', 'string', 'max:2000'],
        ];
    }
}

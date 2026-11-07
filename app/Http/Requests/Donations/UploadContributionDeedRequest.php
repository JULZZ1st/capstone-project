<?php

namespace App\Http\Requests\Donations;

use Illuminate\Foundation\Http\FormRequest;

class UploadContributionDeedRequest extends FormRequest
{
    protected $errorBag = 'deedUpload';

    public function authorize(): bool
    {
        return $this->user()?->can('uploadDeed', $this->route('contribution')) ?? false;
    }

    public function rules(): array
    {
        return [
            'deed_of_donation' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ];
    }

    protected function getRedirectUrl(): string
    {
        return route('donations.show', $this->route('contribution'));
    }
}

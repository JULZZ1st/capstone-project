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
            foreach ($this->route('contribution')->verificationErrors() as $field => $message) {
                $validator->errors()->add($field, $message);
            }
        });
    }

    protected function getRedirectUrl(): string
    {
        return route('donations.show', $this->route('contribution'));
    }
}

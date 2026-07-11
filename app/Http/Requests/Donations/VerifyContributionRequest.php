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
}

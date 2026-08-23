<?php

namespace App\Http\Requests\SystemSecurity;

use App\Models\SecurityActivityEvent;
use Illuminate\Foundation\Http\FormRequest;

class DeactivateStaffAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('updateSecuritySettings', SecurityActivityEvent::class) ?? false;
    }

    public function rules(): array
    {
        return [];
    }
}

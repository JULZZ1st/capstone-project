<?php

namespace App\Http\Requests\SystemSecurity;

use App\Models\SecurityActivityEvent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class CreateStaffAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('updateSecuritySettings', SecurityActivityEvent::class) ?? false;
    }

    public function rules(): array
    {
        $emailRule = config('auth_defaults.email.validate_dns') ? 'email:rfc,dns' : 'email:rfc';

        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', $emailRule, 'max:255', 'unique:users,email'],
            'role' => ['required', 'string', 'in:admin,secretary,treasurer'],
            'password' => ['required', 'confirmed', Password::min(12)],
        ];
    }
}

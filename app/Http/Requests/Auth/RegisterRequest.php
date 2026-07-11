<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $emailRule = config('auth_defaults.email.validate_dns') ? 'email:rfc,dns' : 'email:rfc';
        $passwordRule = Password::min(config('auth_defaults.password.min'))
            ->mixedCase()
            ->numbers()
            ->symbols();

        if (config('auth_defaults.password.check_uncompromised')) {
            $passwordRule->uncompromised();
        }

        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', $emailRule, 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', $passwordRule],
            'terms' => ['accepted'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'first_name' => trim((string) $this->input('first_name')),
            'last_name' => trim((string) $this->input('last_name')),
            'email' => Str::lower((string) $this->input('email')),
        ]);
    }
}

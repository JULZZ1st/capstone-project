<?php

namespace App\Http\Requests\Workspace;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class CreateWorkspaceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(Role::ADMIN) ?? false;
    }

    public function rules(): array
    {
        $emailRule = config('auth_defaults.email.validate_dns') ? 'email:rfc,dns' : 'email:rfc';

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'alpha_dash', 'max:100', 'unique:workspaces,slug'],
            'admin_first_name' => ['required', 'string', 'max:100'],
            'admin_last_name' => ['required', 'string', 'max:100'],
            'admin_email' => ['required', 'string', $emailRule, 'max:255', 'unique:users,email'],
            'admin_password' => ['required', 'confirmed', Password::min(12)],
        ];
    }
}

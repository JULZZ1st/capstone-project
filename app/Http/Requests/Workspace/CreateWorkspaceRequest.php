<?php

namespace App\Http\Requests\Workspace;

use App\Support\Workspaces\WorkspaceAuthorizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class CreateWorkspaceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && app(WorkspaceAuthorizer::class)->canManageWorkspaces($this->user());
    }

    public function rules(): array
    {
        $emailRule = config('auth_defaults.email.validate_dns') ? 'email:rfc,dns' : 'email:rfc';

        return [
            'name' => ['required', 'string', 'max:150'],
            'slug' => ['nullable', 'string', 'max:150', 'alpha_dash', 'unique:workspaces,slug'],
            'admin_first_name' => ['required', 'string', 'max:100'],
            'admin_last_name' => ['required', 'string', 'max:100'],
            'admin_email' => ['required', 'string', $emailRule, 'max:255', 'unique:users,email'],
            'admin_password' => ['required', 'confirmed', Password::min(12)],
        ];
    }
}

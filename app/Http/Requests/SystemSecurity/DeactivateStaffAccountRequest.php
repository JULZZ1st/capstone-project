<?php

namespace App\Http\Requests\SystemSecurity;

use App\Models\SecurityActivityEvent;
use App\Support\Workspaces\WorkspaceAuthorizer;
use Illuminate\Foundation\Http\FormRequest;

class DeactivateStaffAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $target = $this->route('user');

        return $user !== null
            && $target !== null
            && $user->can('updateSecuritySettings', SecurityActivityEvent::class)
            && app(WorkspaceAuthorizer::class)->canManageMember($user, $target);
    }

    public function rules(): array
    {
        return [];
    }
}

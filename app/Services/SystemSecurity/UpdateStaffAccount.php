<?php

namespace App\Services\SystemSecurity;

use App\Models\Role;
use App\Models\SecurityActivityEvent;
use App\Models\User;

class UpdateStaffAccount
{
    public function handle(User $user, array $data, User $actor): User
    {
        $role = Role::query()->firstOrCreate(
            ['name' => $data['role']],
            ['display_name' => ucfirst($data['role']), 'description' => ucfirst($data['role'])]
        );

        $user->forceFill([
            'role_id' => $role->id,
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'name' => $data['first_name'].' '.$data['last_name'],
            'email' => $data['email'],
            'status' => $data['status'] ?? $user->status,
        ])->save();

        SecurityActivityEvent::query()->create([
            'user_id' => $user->id,
            'actor_id' => $actor->id,
            'action' => 'staff_account_updated',
            'summary' => sprintf('%s updated staff account %s', $actor->name, $user->email),
            'metadata' => ['role' => $role->name],
            'ip_address' => request()->ip(),
            'occurred_at' => now(),
        ]);

        return $user;
    }
}

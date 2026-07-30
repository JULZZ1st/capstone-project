<?php

namespace App\Services\SystemSecurity;

use App\Models\SecurityActivityEvent;
use App\Models\User;
use App\Models\UserSecurityProfile;
use Illuminate\Http\Request;

class ToggleTwoFactorEnforcement
{
    public function handle(User $user, bool $enforced, User $actor, Request $request): UserSecurityProfile
    {
        $profile = UserSecurityProfile::query()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'two_factor_enabled' => false,
                'two_factor_enforced' => false,
                'security_key_suffix' => str_pad((string) (($user->id * 82913) % 1000000), 6, '0', STR_PAD_LEFT),
            ],
        );

        $previous = $profile->two_factor_enforced;

        $profile->forceFill([
            'two_factor_enforced' => $enforced,
            'two_factor_enabled' => $enforced ? true : $profile->two_factor_enabled,
            'two_factor_verified_at' => $enforced && ! $profile->two_factor_verified_at ? now() : $profile->two_factor_verified_at,
        ])->save();

        SecurityActivityEvent::query()->create([
            'user_id' => $user->id,
            'actor_id' => $actor->id,
            'action' => $enforced ? 'two_factor_enforced' : 'two_factor_relaxed',
            'summary' => sprintf(
                '%s %s two-factor enforcement for %s.',
                $actor->name,
                $enforced ? 'enabled' : 'disabled',
                $user->name,
            ),
            'metadata' => [
                'previous_two_factor_enforced' => $previous,
                'current_two_factor_enforced' => $enforced,
            ],
            'ip_address' => $request->ip(),
            'occurred_at' => now(),
        ]);

        return $profile;
    }
}

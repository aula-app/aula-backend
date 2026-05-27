<?php

namespace App\Services;

use App\Models\LegacyUser;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Contracts\User as SocialiteUser;

class SsoUserService
{
    /**
     * Find an existing user by email or sso_sub in a single query.
     *
     * If both columns match different rows (corrupt state), the sso_sub match
     * takes priority and a warning is logged so the duplicate can be cleaned up
     * manually.
     */
    public function resolveUser(?string $email, string $sub): ?LegacyUser
    {
        $candidates = LegacyUser::where('email', $email)
            ->orWhere('sso_sub', $sub)
            ->get();

        if ($candidates->isEmpty()) {
            return null;
        }

        if ($candidates->count() === 1) {
            return $candidates->first();
        }

        $bySubMatch = $candidates->firstWhere('sso_sub', $sub);
        $byEmailMatch = $candidates->firstWhere('email', $email);

        if ($bySubMatch && $byEmailMatch && $bySubMatch->id !== $byEmailMatch->id) {
            Log::warning('SSO: email and sso_sub match different users — prioritising sso_sub match.', [
                'email' => $email,
                'sub' => $sub,
                'sso_sub_user' => $bySubMatch->id,
                'email_user' => $byEmailMatch->id,
            ]);
        }

        return $bySubMatch ?? $byEmailMatch;
    }

    /**
     * Create a new user from the SSO claims and enrol them in the standard room.
     */
    public function provisionUser(SocialiteUser $socialiteUser): LegacyUser
    {
        /** @var Tenant|null $tenant */
        $tenant = tenant();
        $user = LegacyUser::fromSocialiteUser($socialiteUser, $tenant?->sso_provider);
        $user->save();

        $this->addToStandardRoom($user);

        return $user;
    }

    /**
     * Add a newly provisioned user to the standard room (type=1, the school room).
     * Mirrors legacy User::addUserToStandardRoom() logic.
     */
    public function addToStandardRoom(LegacyUser $user): void
    {
        $room = DB::table('au_rooms')->where('type', 1)->first(['id', 'hash_id']);

        if ($room === null) {
            return;
        }

        DB::table('au_rel_rooms_users')->insertOrIgnore([
            'room_id' => $room->id,
            'user_id' => $user->id,
            'status' => 1,
            'created' => now(),
            'last_update' => now(),
            'updater_id' => 0,
        ]);

        /** @var list<array{role?: int, room?: string}> $roles */
        $roles = json_decode($user->roles, true) ?: [];
        $roles = array_values(array_filter($roles, fn (array $r) => ($r['room'] ?? null) !== $room->hash_id));
        $roles[] = ['role' => 20, 'room' => $room->hash_id];

        DB::table('au_users_basedata')
            ->where('id', $user->id)
            ->update(['roles' => json_encode($roles), 'last_update' => now()]);
    }
}

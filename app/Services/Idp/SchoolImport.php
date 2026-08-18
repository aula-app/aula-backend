<?php

declare(strict_types=1);

namespace App\Services\Idp;

use App\Enums\UserLevel;
use App\Enums\UserStatus;
use App\Models\IdpDirectoryEntry;
use App\Models\LegacyUser;
use App\Models\Tenant;
use App\Services\Idp\Dto\IdpGroup;
use App\Services\Idp\Dto\IdpGroupRef;
use App\Services\Idp\Dto\IdpUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Pulls a whole school from an identity provider into a tenant in one pass.
 *
 * This is how a school's people and classes actually arrive. Webhooks only
 * report *changes* — no provider replays an existing roster — so without this a
 * freshly connected school would stay empty until people trickled in one login
 * at a time.
 *
 * Runs synchronously on the first SSO login (see
 * SsoController::bootstrapIdpTenant) so nobody reaches a half-populated aula.
 * Progress is written to the tenant record for the frontend to poll.
 *
 * Provider-agnostic: everything it reads comes through IdentityDirectory, so
 * which upstream a school syncs from changes nothing here.
 *
 * Idempotent throughout — re-running converges rather than duplicating, so a
 * failed import can simply be run again.
 */
final class SchoolImport
{
    /** Queued but not yet picked up by a worker. */
    public const string STATUS_PENDING = 'pending';

    public const string STATUS_RUNNING = 'running';

    public const string STATUS_COMPLETED = 'completed';

    public const string STATUS_FAILED = 'failed';

    public function __construct(
        private readonly IdpProviders $providers,
        private readonly RoomEnrolment $rooms,
        private readonly TenantResolver $resolver,
        private readonly RoleMap $roles,
    ) {}

    /**
     * Import the school bound to this tenant. Must be called with tenancy
     * initialised.
     */
    public function run(Tenant $tenant): void
    {
        $schoolId = (string) $tenant->idp_school_id;
        $provider = $this->providers->forTenant($tenant);

        if ($schoolId === '' || $provider === null) {
            return;
        }

        $directory = $this->providers->directory($provider);

        $this->markRunning($tenant);

        try {
            $groups = $directory->groups($schoolId);
            $users = $directory->users($schoolId);

            $roomCount = $this->importRooms($tenant, $groups);
            $userCount = $this->importUsers($tenant, $provider, $users, $groups);
        } catch (Throwable $e) {
            $this->markFailed($tenant, $e);

            throw $e;
        }

        $this->markCompleted($tenant, $roomCount, $userCount);

        Log::info('IdP: school import finished', [
            'tenant' => $tenant->instance_code,
            'provider' => $provider,
            'rooms' => $roomCount,
            'users' => $userCount,
        ]);
    }

    /**
     * Provider groups become aula rooms.
     *
     * @param  list<IdpGroup>  $groups
     */
    private function importRooms(Tenant $tenant, array $groups): int
    {
        foreach ($groups as $group) {
            $this->rooms->upsertRoom($group->id, $group->name, $group->isActive());
            $this->resolver->remember(IdpDirectoryEntry::TYPE_GROUP, $group->id, $tenant->id);
        }

        return count($groups);
    }

    /**
     * Import everyone, merging the school's user list with what the group
     * member lists reveal.
     *
     * Both are needed: a provider may expose names only on group members, and
     * may list someone in a group who is absent from the user listing entirely.
     * Reading either alone loses data.
     *
     * @param  list<IdpUser>  $users
     * @param  list<IdpGroup>  $groups
     */
    private function importUsers(Tenant $tenant, string $provider, array $users, array $groups): int
    {
        $merged = [];

        foreach ($groups as $group) {
            foreach ($group->members as $member) {
                $withGroup = new IdpUser(
                    id: $member->id,
                    name: $member->name,
                    role: $member->role,
                    status: $member->status,
                    sourceSystemIdentifier: $member->sourceSystemIdentifier,
                    groups: [new IdpGroupRef($group->id, $group->name)],
                    pseudonym: $member->pseudonym,
                );

                $merged[$member->id] = isset($merged[$member->id])
                    ? $merged[$member->id]->mergedWith($withGroup)
                    : $withGroup;
            }
        }

        foreach ($users as $user) {
            $merged[$user->id] = isset($merged[$user->id])
                ? $merged[$user->id]->mergedWith($user)
                : $user;
        }

        foreach ($merged as $user) {
            $this->importUser($tenant, $provider, $user);
        }

        return count($merged);
    }

    /**
     * Create or converge one user, then put them in their rooms.
     */
    public function importUser(Tenant $tenant, string $provider, IdpUser $person): LegacyUser
    {
        $user = LegacyUser::where('idp_user_id', $person->id)->first();

        if ($user === null) {
            $user = new LegacyUser;
            $user->idp_user_id = $person->id;
            $user->username = $this->uniqueUsername($person);
            $user->hash_id = md5($person->id.(string) microtime(true).random_int(100, 10000000));
            // Identity providers need not expose an email address, and their
            // users need not have one. The column stays null; `idp_user_id` is
            // the identifier.
            $user->email = null;
        }

        $displayName = $person->displayName();

        $user->displayname = $displayName !== '' ? $displayName : (string) $user->username;
        $user->realname = $person->realName() ?? $user->realname;
        $user->status = $person->isActive() ? UserStatus::Active : UserStatus::Archived;

        // Never demote an admin. The first person to sign in takes over the
        // tenant admin, and to the provider they are an ordinary teacher —
        // letting the role map write their userlevel would strip their own
        // school's administration from them mid-import.
        if (($user->userlevel?->value ?? 0) < UserLevel::Admin->value) {
            $user->userlevel = $this->roles->userlevel($provider, $person->role);
        }

        $user->save();

        $role = $this->roles->roomRole($provider, $person->role);

        $this->enrolInSchoolRoom($user, $role);
        $this->rooms->syncUserRooms($user->id, $person->groups, $role);

        $this->resolver->remember(IdpDirectoryEntry::TYPE_USER, $person->id, $tenant->id);

        return $user;
    }

    /**
     * Everyone belongs to the school-wide room (`au_rooms.type = 1`) as well as
     * to their classes, the same as any locally provisioned user.
     */
    private function enrolInSchoolRoom(LegacyUser $user, int $role): void
    {
        $room = DB::table('au_rooms')->where('type', 1)->first(['id', 'hash_id']);

        if ($room === null) {
            return;
        }

        $this->rooms->enrol($user->id, (int) $room->id, (string) $room->hash_id, $role);
    }

    /**
     * Usernames are unique and there is no email to derive one from, so the
     * person's name is used with a slice of their provider id.
     */
    private function uniqueUsername(IdpUser $person): string
    {
        $base = Str::slug($person->displayName(), '.');

        if ($base === '') {
            $base = 'user';
        }

        $base = substr($base, 0, 40).'.'.substr((string) preg_replace('/[^a-z0-9]/', '', strtolower($person->id)), 0, 6);

        $candidate = $base;
        $suffix = 1;

        while (LegacyUser::where('username', $candidate)->exists()) {
            $candidate = $base.'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }

    private function markRunning(Tenant $tenant): void
    {
        $tenant->update([
            'idp_import_status' => self::STATUS_RUNNING,
            'idp_import_error' => null,
            'idp_import_started_at' => now(),
            'idp_import_finished_at' => null,
        ]);
    }

    private function markCompleted(Tenant $tenant, int $rooms, int $users): void
    {
        $tenant->update([
            'idp_import_status' => self::STATUS_COMPLETED,
            'idp_import_rooms' => $rooms,
            'idp_import_users' => $users,
            'idp_import_error' => null,
            'idp_import_finished_at' => now(),
        ]);
    }

    private function markFailed(Tenant $tenant, Throwable $e): void
    {
        Log::error('IdP: school import failed', [
            'tenant' => $tenant->instance_code,
            'error' => $e->getMessage(),
        ]);

        $tenant->update([
            'idp_import_status' => self::STATUS_FAILED,
            'idp_import_error' => substr($e->getMessage(), 0, 1000),
            'idp_import_finished_at' => now(),
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Idp\Migration;

use App\Models\LegacyUser;
use App\Models\Tenant;
use App\Services\Idp\Dto\IdpGroup;
use App\Services\Idp\Dto\IdpUser;
use App\Services\Idp\IdpProviders;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Works out how a school's existing aula rows line up with its directory.
 *
 * Proposes only. Nothing here changes a user or a room — it writes candidates
 * for a human to confirm, because the only thing the two sides have in common
 * is names, and a wrong name match hands somebody another person's account and
 * everything they wrote.
 *
 * Must be called with tenancy initialised.
 */
final class MergeProposalBuilder
{
    public const string KIND_USER = 'user';

    public const string KIND_ROOM = 'room';

    /** Exactly one candidate on each side. */
    public const string OUTCOME_CONFIDENT = 'confident';

    /** More than one candidate either way — a human has to choose. */
    public const string OUTCOME_AMBIGUOUS = 'ambiguous';

    /** Nothing to match against. */
    public const string OUTCOME_NONE = 'none';

    public const string NAME_REAL = 'real';

    /**
     * A generated stand-in the directory returns when it will not disclose a
     * real name. Can never be matched: it is not what anyone is called in aula.
     */
    public const string NAME_PSEUDONYM = 'pseudonym';

    public function __construct(
        private readonly IdpProviders $providers,
    ) {}

    /**
     * Replace any existing proposal for this tenant with a fresh one.
     *
     * @return array<string, int> counts by outcome, for the caller to report
     */
    public function build(Tenant $tenant): array
    {
        $provider = $this->providers->forTenant($tenant);
        $schoolId = (string) $tenant->idp_school_id;

        if ($provider === null || $schoolId === '') {
            throw new RuntimeException('The school must be connected to a provider before a proposal can be built.');
        }

        $directory = $this->providers->directory($provider);

        $groups = $directory->groups($schoolId);
        $users = $this->mergeGroupMembers($directory->users($schoolId), $groups);

        DB::table('idp_merge_candidates')->truncate();

        $counts = $this->proposeRooms($groups);

        foreach ($this->proposeUsers($users) as $outcome => $count) {
            $counts[$outcome] = ($counts[$outcome] ?? 0) + $count;
        }

        Log::info('IdP: built a merge proposal', [
            'tenant' => $tenant->instance_code,
            'counts' => $counts,
        ]);

        return $counts;
    }

    /**
     * Fold the group member lists into the user list.
     *
     * A directory may expose real names only on group members while the user
     * listing carries a pseudonym, and may list somebody in a group who is
     * absent from the user listing altogether. Both are needed or the proposal
     * silently loses people.
     *
     * @param  list<IdpUser>  $users
     * @param  list<IdpGroup>  $groups
     * @return list<IdpUser>
     */
    private function mergeGroupMembers(array $users, array $groups): array
    {
        $merged = [];

        foreach ($groups as $group) {
            foreach ($group->members as $member) {
                $merged[$member->id] = isset($merged[$member->id])
                    ? $merged[$member->id]->mergedWith($member)
                    : $member;
            }
        }

        foreach ($users as $user) {
            $merged[$user->id] = isset($merged[$user->id])
                ? $merged[$user->id]->mergedWith($user)
                : $user;
        }

        return array_values($merged);
    }

    /**
     * @param  list<IdpGroup>  $groups
     * @return array<string, int>
     */
    private function proposeRooms(array $groups): array
    {
        $local = [];

        foreach (DB::table('au_rooms')->whereNull('idp_group_id')->get(['id', 'room_name']) as $room) {
            // The school-wide room is aula's own and is never a class.
            $local[] = ['id' => (int) $room->id, 'name' => (string) $room->room_name];
        }

        $provider = array_map(
            fn (IdpGroup $group): array => [
                'id' => $group->id,
                'name' => $group->name,
                'keys' => NameKey::keys([$group->name]),
                'name_kind' => self::NAME_REAL,
            ],
            $groups,
        );

        return $this->propose(self::KIND_ROOM, $provider, array_map(
            fn (array $room): array => $room + ['keys' => NameKey::keys([$room['name']])],
            $local,
        ));
    }

    /**
     * @param  list<IdpUser>  $users
     * @return array<string, int>
     */
    private function proposeUsers(array $users): array
    {
        $local = LegacyUser::whereNull('idp_user_id')
            ->get(['id', 'realname', 'displayname'])
            ->map(fn (LegacyUser $user): array => [
                'id' => (int) $user->id,
                'name' => (string) ($user->realname ?: $user->displayname),
                'keys' => NameKey::keys([$user->realname, $user->displayname]),
            ])
            ->all();

        $provider = [];

        foreach ($users as $user) {
            $real = $user->realName();

            $provider[] = [
                'id' => $user->id,
                'name' => $user->displayName(),
                // Only a real name is worth comparing. A pseudonym would match
                // nothing, and pretending otherwise hides why.
                'keys' => $real === null ? [] : NameKey::keys([$real, $user->name->display()]),
                'name_kind' => $real === null ? self::NAME_PSEUDONYM : self::NAME_REAL,
            ];
        }

        return $this->propose(self::KIND_USER, $provider, $local);
    }

    /**
     * Pair two sides by name key and write the result.
     *
     * A pairing is confident only when each side sees exactly one of the other.
     * One provider person matching two aula rows is ambiguous; so is one aula
     * row being the only match for two provider people, because applying both
     * would merge two people into one account.
     *
     * @param  list<array{id: string, name: string, keys: list<string>, name_kind: string}>  $provider
     * @param  list<array{id: int, name: string, keys: list<string>}>  $local
     * @return array<string, int>
     */
    private function propose(string $kind, array $provider, array $local): array
    {
        /** @var array<string, list<int>> $localByKey */
        $localByKey = [];

        foreach ($local as $index => $row) {
            foreach ($row['keys'] as $key) {
                $localByKey[$key][] = $index;
            }
        }

        /** @var array<int, list<int>> $claimedBy provider index list, by local index */
        $claimedBy = [];
        $matches = [];

        foreach ($provider as $pIndex => $row) {
            $candidates = [];

            foreach ($row['keys'] as $key) {
                foreach ($localByKey[$key] ?? [] as $localIndex) {
                    $candidates[$localIndex] = true;
                }
            }

            $matches[$pIndex] = array_keys($candidates);

            foreach ($matches[$pIndex] as $localIndex) {
                $claimedBy[$localIndex][] = $pIndex;
            }
        }

        $counts = [self::OUTCOME_CONFIDENT => 0, self::OUTCOME_AMBIGUOUS => 0, self::OUTCOME_NONE => 0];
        $rows = [];
        $matchedLocal = [];
        $now = now();

        foreach ($provider as $pIndex => $row) {
            $candidates = $matches[$pIndex];
            $localIndex = $candidates[0] ?? null;

            $outcome = match (true) {
                $candidates === [] => self::OUTCOME_NONE,
                count($candidates) > 1 => self::OUTCOME_AMBIGUOUS,
                count($claimedBy[$localIndex] ?? []) > 1 => self::OUTCOME_AMBIGUOUS,
                default => self::OUTCOME_CONFIDENT,
            };

            if ($outcome !== self::OUTCOME_NONE && $localIndex !== null) {
                $matchedLocal[$localIndex] = true;
            }

            $counts[$outcome]++;
            $rows[] = [
                'kind' => $kind,
                'idp_id' => $row['id'],
                'idp_name' => $row['name'],
                'idp_name_kind' => $row['name_kind'],
                'local_id' => $outcome === self::OUTCOME_NONE ? null : ($local[$localIndex]['id'] ?? null),
                'local_name' => $outcome === self::OUTCOME_NONE ? null : ($local[$localIndex]['name'] ?? null),
                'outcome' => $outcome,
                // Only an unambiguous pairing is pre-selected. Everything else
                // is a question for the admin, not a default.
                'decision' => $outcome === self::OUTCOME_CONFIDENT ? 'merge' : null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // Whatever nothing pointed at exists in aula alone: it keeps working as
        // it does today and can still be linked later by its owner.
        foreach ($local as $index => $row) {
            if (isset($matchedLocal[$index])) {
                continue;
            }

            $rows[] = [
                'kind' => $kind,
                'idp_id' => null,
                'idp_name' => null,
                'idp_name_kind' => null,
                'local_id' => $row['id'],
                'local_name' => $row['name'],
                'outcome' => self::OUTCOME_NONE,
                'decision' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('idp_merge_candidates')->insert($chunk);
        }

        return $counts;
    }
}

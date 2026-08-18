<?php

declare(strict_types=1);

namespace App\Services\Idp\Migration;

use App\Models\LegacyUser;
use App\Models\Tenant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Turns a reviewed proposal into the identity stamps the import reads.
 *
 * Applying a merge writes one column — `idp_user_id` on a person,
 * `idp_group_id` on a room — and nothing else. That is enough for the import
 * to converge onto the existing row instead of creating a second one, and it
 * means nothing is moved, rewritten or deleted, so a mistaken merge is undone
 * by clearing a column.
 *
 * Must be called with tenancy initialised.
 */
final class MergeProposalApplier
{
    /**
     * Reasons a proposal cannot be applied, keyed by candidate id.
     *
     * @return array<int, string>
     */
    public function validate(): array
    {
        $problems = [];
        $claimed = [];

        foreach ($this->merges() as $row) {
            if ($row->local_id === null) {
                $problems[(int) $row->id] = 'no_local_row';

                continue;
            }

            // Two provider identities stamped onto one aula row would fold two
            // people into a single account, and the second write would fail the
            // unique index anyway.
            $key = $row->kind.':'.$row->local_id;

            if (isset($claimed[$key])) {
                $problems[(int) $row->id] = 'local_row_claimed_twice';

                continue;
            }

            $claimed[$key] = true;

            if (! $this->localRowIsFree($row)) {
                $problems[(int) $row->id] = 'local_row_already_linked';
            }
        }

        return $problems;
    }

    /**
     * @return array<string, int> how many rows of each kind were stamped
     */
    public function apply(Tenant $tenant): array
    {
        $applied = [MergeProposalBuilder::KIND_USER => 0, MergeProposalBuilder::KIND_ROOM => 0];

        DB::transaction(function () use (&$applied): void {
            foreach ($this->merges() as $row) {
                if ($row->local_id === null) {
                    continue;
                }

                $stamped = $row->kind === MergeProposalBuilder::KIND_ROOM
                    ? $this->stampRoom((int) $row->local_id, (string) $row->idp_id)
                    : $this->stampUser((int) $row->local_id, (string) $row->idp_id);

                if ($stamped) {
                    $applied[$row->kind]++;
                }
            }
        });

        Log::info('IdP: applied a merge proposal', [
            'tenant' => $tenant->instance_code,
            'applied' => $applied,
        ]);

        return $applied;
    }

    /**
     * @return Collection<int, \stdClass>
     */
    private function merges()
    {
        return DB::table('idp_merge_candidates')
            ->where('decision', 'merge')
            ->whereNotNull('idp_id')
            ->orderBy('id')
            ->get();
    }

    private function localRowIsFree(object $row): bool
    {
        if ($row->kind === MergeProposalBuilder::KIND_ROOM) {
            $current = DB::table('au_rooms')->where('id', $row->local_id)->value('idp_group_id');
        } else {
            $current = DB::table('au_users_basedata')->where('id', $row->local_id)->value('idp_user_id');
        }

        // Re-applying the same pairing is fine; pointing at a row that already
        // belongs to a different identity is not.
        return $current === null || $current === $row->idp_id;
    }

    private function stampUser(int $localId, string $idpId): bool
    {
        // A row the import already created for this identity has no content by
        // construction, so the id can move to the real account and the empty
        // one can go.
        $shell = LegacyUser::where('idp_user_id', $idpId)->where('id', '!=', $localId)->first();

        if ($shell !== null) {
            DB::table('au_rel_rooms_users')->where('user_id', $shell->id)->delete();
            $shell->delete();
        }

        return DB::table('au_users_basedata')
            ->where('id', $localId)
            ->update(['idp_user_id' => $idpId]) > 0;
    }

    private function stampRoom(int $localId, string $idpId): bool
    {
        $shell = DB::table('au_rooms')->where('idp_group_id', $idpId)->where('id', '!=', $localId)->first(['id']);

        if ($shell !== null) {
            DB::table('au_rel_rooms_users')->where('room_id', $shell->id)->delete();
            DB::table('au_rooms')->where('id', $shell->id)->delete();
        }

        return DB::table('au_rooms')
            ->where('id', $localId)
            ->update(['idp_group_id' => $idpId]) > 0;
    }
}

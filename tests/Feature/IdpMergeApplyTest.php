<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\ImportSchoolForTenant;
use App\Models\LegacyUser;
use App\Models\Tenant;
use App\Services\LegacyJwtService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\CreatesTestTenant;
use Tests\TestCase;

/**
 * Reviewing and applying a merge proposal.
 *
 * Applying decides who owns which account, so the cases that matter are the
 * ones it must refuse, and the guarantee that a merge writes one column and
 * touches nothing else.
 */
class IdpMergeApplyTest extends TestCase
{
    use CreatesTestTenant;

    private const string ADMIN = 'apply_admin';

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureTestTenantExists();
        self::$testTenant->refresh();
        self::$testTenant->update([
            'sso_provider' => 'eduplaces',
            'idp_school_id' => 'school-apply-test',
            'idp_migration_status' => Tenant::IDP_MIGRATION_REVIEWING,
        ]);

        Queue::fake();
        $this->clean();
    }

    protected function tearDown(): void
    {
        $this->clean();
        Tenant::where('id', self::$testTenant->id)->update([
            'idp_school_id' => null,
            'idp_migration_status' => null,
            'idp_import_status' => null,
        ]);
        parent::tearDown();
    }

    public function test_applying_a_merge_stamps_the_identity_on_the_existing_account(): void
    {
        $userId = $this->seedUser('apply_pupil', 20);
        $this->seedCandidate('user', 'p1', $userId, 'merge');

        $this->postJson('/api/v2/auth/idp/merge-proposal/apply', [], $this->adminHeaders())
            ->assertOk()->assertJsonPath('applied.user', 1);

        self::$testTenant->run(function () use ($userId) {
            $user = LegacyUser::find($userId);

            // One column. The account, its content and its password are as
            // they were.
            $this->assertSame('p1', $user->idp_user_id);
            $this->assertNull($user->sso_sub);
            $this->assertNotEmpty($user->pw);
        });
    }

    public function test_it_refuses_a_proposal_that_points_two_people_at_one_account(): void
    {
        $userId = $this->seedUser('apply_pupil', 20);
        $this->seedCandidate('user', 'p1', $userId, 'merge');
        $this->seedCandidate('user', 'p2', $userId, 'merge');

        $response = $this->postJson('/api/v2/auth/idp/merge-proposal/apply', [], $this->adminHeaders());

        // Folding two people into one account is the worst outcome available,
        // so nothing is applied at all.
        $response->assertStatus(422)->assertJsonPath('error', 'proposal_invalid');

        self::$testTenant->run(function () use ($userId) {
            $this->assertNull(LegacyUser::find($userId)->idp_user_id);
        });
    }

    public function test_it_refuses_to_repoint_an_account_that_is_already_linked(): void
    {
        $userId = $this->seedUser('apply_pupil', 20);
        self::$testTenant->run(fn () => LegacyUser::where('id', $userId)->update(['idp_user_id' => 'someone-else']));
        $this->seedCandidate('user', 'p1', $userId, 'merge');

        $this->postJson('/api/v2/auth/idp/merge-proposal/apply', [], $this->adminHeaders())
            ->assertStatus(422)->assertJsonPath('error', 'proposal_invalid');
    }

    public function test_a_merge_absorbs_a_row_the_import_already_created(): void
    {
        $realId = $this->seedUser('apply_pupil', 20);
        $shellId = (int) self::$testTenant->run(function () {
            $shell = new LegacyUser;
            $shell->username = 'apply_shell';
            $shell->displayname = 'Shell';
            $shell->idp_user_id = 'p1';
            $shell->status = LegacyUser::STATUS_ACTIVE;
            $shell->userlevel = 20;
            $shell->hash_id = md5('apply_shell');
            $shell->save();

            return $shell->id;
        });

        $this->seedCandidate('user', 'p1', $realId, 'merge');

        $this->postJson('/api/v2/auth/idp/merge-proposal/apply', [], $this->adminHeaders())->assertOk();

        self::$testTenant->run(function () use ($realId, $shellId) {
            // The provider-created row has no content by construction, so the
            // identity moves to the real account and the empty one goes.
            $this->assertSame('p1', LegacyUser::find($realId)->idp_user_id);
            $this->assertNull(LegacyUser::find($shellId));
        });
    }

    public function test_unconfirmed_rows_are_left_for_the_import_to_create(): void
    {
        $userId = $this->seedUser('apply_pupil', 20);
        $this->seedCandidate('user', 'p1', $userId, null);

        $this->postJson('/api/v2/auth/idp/merge-proposal/apply', [], $this->adminHeaders())
            ->assertOk()->assertJsonPath('applied.user', 0);

        self::$testTenant->run(fn () => $this->assertNull(LegacyUser::find($userId)->idp_user_id));
    }

    public function test_applying_starts_the_import(): void
    {
        $this->postJson('/api/v2/auth/idp/merge-proposal/apply', [], $this->adminHeaders())->assertOk();

        Queue::assertPushed(ImportSchoolForTenant::class);
        $this->assertSame(Tenant::IDP_MIGRATION_IMPORTING, self::$testTenant->fresh()->idp_migration_status);
    }

    public function test_an_admin_can_repoint_a_row_by_hand(): void
    {
        $wrongId = $this->seedUser('apply_wrong', 20);
        $rightId = $this->seedUser('apply_right', 20);
        $candidateId = $this->seedCandidate('user', 'p1', $wrongId, null);

        $this->postJson('/api/v2/auth/idp/merge-proposal/decisions', [
            'decisions' => [['id' => $candidateId, 'decision' => 'merge', 'local_id' => $rightId]],
        ], $this->adminHeaders())->assertOk();

        $this->postJson('/api/v2/auth/idp/merge-proposal/apply', [], $this->adminHeaders())->assertOk();

        self::$testTenant->run(function () use ($wrongId, $rightId) {
            // Picking a target by hand is the only way to match somebody the
            // name comparison could not, or to correct a wrong guess.
            $this->assertSame('p1', LegacyUser::find($rightId)->idp_user_id);
            $this->assertNull(LegacyUser::find($wrongId)->idp_user_id);
        });
    }

    public function test_a_non_admin_can_neither_see_nor_apply_the_proposal(): void
    {
        $pupilId = $this->seedUser('apply_pupil', 20);
        $headers = $this->headersFor($pupilId);

        $this->getJson('/api/v2/auth/idp/merge-proposal', $headers)->assertStatus(403);
        $this->postJson('/api/v2/auth/idp/merge-proposal/apply', [], $headers)->assertStatus(403);
    }

    public function test_progress_reports_how_much_of_the_school_is_linked(): void
    {
        $this->seedUser('apply_pupil', 20);
        $linked = $this->seedUser('apply_linked', 20);
        self::$testTenant->run(fn () => LegacyUser::where('id', $linked)->update(['idp_user_id' => 'p-linked']));

        $response = $this->getJson('/api/v2/auth/idp/migration-progress', $this->adminHeaders());

        $response->assertOk();
        $this->assertGreaterThanOrEqual(1, $response->json('linked'));
        $this->assertGreaterThanOrEqual(1, $response->json('not_yet_linked'));
    }

    // =========================================================
    // Helpers
    // =========================================================

    private function seedCandidate(string $kind, string $idpId, ?int $localId, ?string $decision): int
    {
        return (int) self::$testTenant->run(fn () => DB::table('idp_merge_candidates')->insertGetId([
            'kind' => $kind,
            'idp_id' => $idpId,
            'idp_name' => 'Provider '.$idpId,
            'idp_name_kind' => 'real',
            'local_id' => $localId,
            'local_name' => 'Aula row',
            'outcome' => 'confident',
            'decision' => $decision,
            'created_at' => now(),
            'updated_at' => now(),
        ]));
    }

    private function seedUser(string $username, int $level): int
    {
        return (int) self::$testTenant->run(function () use ($username, $level) {
            LegacyUser::where('username', $username)->delete();

            $user = new LegacyUser;
            $user->username = $username;
            $user->displayname = $username;
            $user->pw = password_hash('secret', PASSWORD_BCRYPT);
            $user->status = LegacyUser::STATUS_ACTIVE;
            $user->userlevel = $level;
            $user->hash_id = md5($username.microtime(true));
            $user->save();

            return $user->id;
        });
    }

    /**
     * @return array<string, string>
     */
    private function adminHeaders(): array
    {
        return $this->headersFor($this->seedUser(self::ADMIN, 50));
    }

    /**
     * @return array<string, string>
     */
    private function headersFor(int $userId): array
    {
        $jwt = self::$testTenant->run(
            fn () => app(LegacyJwtService::class)->generateToken(LegacyUser::findOrFail($userId)),
        );

        return ['aula-instance-code' => 'TEST001', 'Authorization' => "Bearer {$jwt}"];
    }

    private function clean(): void
    {
        self::$testTenant->run(function () {
            $ids = LegacyUser::where('username', 'like', 'apply_%')->pluck('id')->all();

            if ($ids !== []) {
                DB::table('au_rel_rooms_users')->whereIn('user_id', $ids)->delete();
                LegacyUser::whereIn('id', $ids)->delete();
            }

            DB::table('idp_merge_candidates')->truncate();
        });
    }
}

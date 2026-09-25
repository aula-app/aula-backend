<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Models\LegacyUser;
use App\Models\Tenant;
use App\UseCases\Idp\ListMergeProposalsUseCase;
use App\UseCases\Idp\MergeProposalFilter;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Passport;
use Tests\Concerns\CreatesTestTenant;
use Tests\TestCase;

/**
 * Covers ListMergeProposalsUseCase, the page of idp_merge_candidates an admin
 * reviews, and the transport MergeProposalController::index() wraps it in.
 *
 * The rows settle which account each directory identity lands on, so the
 * cases here are who may see them, how they are narrowed, and that each user
 * row carries the aula names and avatar a reviewer tells two accounts apart by.
 */
class ListMergeProposalsUseCaseTest extends TestCase
{
    use CreatesTestTenant;

    private const string PREFIX = 'list_';

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureTestTenantExists();
        self::$testTenant->refresh();
        self::$testTenant->update([
            'sso_provider' => 'eduplaces',
            'idp_school_id' => 'school-list-test',
            'idp_migration_status' => Tenant::IDP_MIGRATION_REVIEWING,
        ]);

        $this->clean();
    }

    protected function tearDown(): void
    {
        $this->clean();
        Tenant::where('id', self::$testTenant->id)->update([
            'idp_school_id' => null,
            'idp_migration_status' => null,
        ]);
        parent::tearDown();
    }

    public function test_only_an_admin_may_list_the_proposal(): void
    {
        $this->actAs($this->seedUser('pupil', 20));

        $this->expectException(AuthorizationException::class);

        $this->list(new MergeProposalFilter);
    }

    public function test_each_user_row_carries_the_aula_names(): void
    {
        $userId = $this->seedUser('named', 20, realname: 'Anna Named');
        $this->seedCandidate('user', 'p-named', $userId, localName: 'Anna Named');
        $this->seedCandidate('user', 'p-nobody', null);

        $this->actAsAdmin();

        $rows = collect($this->list(new MergeProposalFilter)->items())->keyBy('idp_id');

        // Read from the account, not from local_name: the builder wrote
        // whichever name it matched on, and a reviewer needs both.
        $this->assertSame('list_named', $rows['p-named']['local_displayname']);
        $this->assertSame('Anna Named', $rows['p-named']['local_realname']);
        $this->assertNull($rows['p-nobody']['local_displayname']);
        $this->assertNull($rows['p-nobody']['local_realname']);
    }

    public function test_each_user_row_carries_the_aula_avatar(): void
    {
        $withAvatar = $this->seedUser('with_avatar', 20);
        $without = $this->seedUser('no_avatar', 20);
        $withDocument = $this->seedUser('with_document', 20);
        $this->seedCandidate('user', 'p-avatar', $withAvatar);
        $this->seedCandidate('user', 'p-plain', $without);
        $this->seedCandidate('user', 'p-document', $withDocument);
        $this->seedMedia($withAvatar, 'face.png', systemType: 0);
        // Not an avatar: only system_type 0 is.
        $this->seedMedia($withDocument, 'notes.pdf', systemType: 1);

        $this->actAsAdmin();

        $rows = collect($this->list(new MergeProposalFilter)->items())->keyBy('local_id');

        $this->assertSame('face.png', $rows[$withAvatar]['local_avatar']);
        $this->assertNull($rows[$without]['local_avatar']);
        $this->assertNull($rows[$withDocument]['local_avatar']);
    }

    public function test_each_user_row_carries_its_aula_rooms(): void
    {
        $inRooms = $this->seedUser('in_rooms', 20);
        $inNone = $this->seedUser('in_none', 20);
        $this->seedCandidate('user', 'p-rooms', $inRooms);
        $this->seedCandidate('user', 'p-none', $inNone);
        $this->seedCandidate('user', 'p-nobody', null);
        $active = $this->seedRoom('Klasse 5a');
        $archived = $this->seedRoom('Klasse 4a', status: 3);
        $this->seedMembership($inRooms, $active);
        $this->seedMembership($inRooms, $archived);

        $this->actAsAdmin();

        $rows = collect($this->list(new MergeProposalFilter)->items())->keyBy('idp_id');

        // Archived rooms are left out.
        $this->assertSame([['id' => $active, 'name' => 'Klasse 5a']], $rows['p-rooms']['local_rooms']);
        $this->assertSame([], $rows['p-none']['local_rooms']);
        $this->assertSame([], $rows['p-nobody']['local_rooms']);
    }

    public function test_each_user_row_carries_its_stored_idp_groups(): void
    {
        $userId = $this->seedUser('grouped', 20);
        $groups = [['id' => 'g1', 'name' => 'Klasse 5A'], ['id' => 'g2', 'name' => 'AG Schach']];
        $this->seedCandidate('user', 'p-grouped', $userId, idpGroups: $groups);
        $this->seedCandidate('user', 'p-plain', null, idpGroups: []);
        $this->seedCandidate('user', null, $userId);

        $this->actAsAdmin();

        $rows = collect($this->list(new MergeProposalFilter)->items());

        $this->assertSame($groups, $rows->firstWhere('idp_id', 'p-grouped')['idp_groups']);
        $this->assertSame([], $rows->firstWhere('idp_id', 'p-plain')['idp_groups']);
        // Aula-only rows get idp_groups [], not null.
        $this->assertSame([], $rows->firstWhere('idp_id', null)['idp_groups']);
    }

    public function test_a_room_row_never_carries_account_details(): void
    {
        $userId = $this->seedUser('with_avatar', 20, realname: 'Room Sharer');
        $this->seedMedia($userId, 'face.png', systemType: 0);
        $this->seedCandidate('user', 'p-user', $userId);
        // au_rooms.id and au_users_basedata.id share a number space, so a
        // room row can carry the same local_id as a user row.
        $this->seedCandidate('room', 'g-room', $userId);

        $this->actAsAdmin();

        $rows = collect($this->list(new MergeProposalFilter)->items())->keyBy('kind');

        $this->assertSame('face.png', $rows['user']['local_avatar']);
        $this->assertSame('Room Sharer', $rows['user']['local_realname']);
        $this->assertNull($rows['room']['local_avatar']);
        $this->assertNull($rows['room']['local_displayname']);
        $this->assertNull($rows['room']['local_realname']);
        $this->assertNull($rows['room']['local_rooms']);
        $this->assertNull($rows['room']['idp_groups']);
    }

    public function test_it_narrows_by_kind_bucket_and_search(): void
    {
        $merged = $this->seedUser('merged', 20);
        $aulaOnly = $this->seedUser('aula_only', 20);
        $this->seedCandidate('user', 'p-merged', $merged, localName: 'Anna Merged');
        $this->seedCandidate('user', 'p-idp-only', null, idpName: 'Ben Provider');
        $this->seedCandidate('user', null, $aulaOnly, localName: 'Carla Aula');
        $this->seedCandidate('room', 'g-merged', 42, localName: 'Room 1a');

        $this->actAsAdmin();

        $this->assertSame(4, $this->list(new MergeProposalFilter)->total());
        $this->assertSame(['room'], $this->column(new MergeProposalFilter(kind: 'room'), 'kind'));
        $this->assertSame(
            ['g-merged', 'p-merged'],
            $this->column(new MergeProposalFilter(bucket: MergeProposalFilter::BUCKET_MERGES), 'idp_id'),
        );
        $this->assertSame(
            ['p-idp-only'],
            $this->column(new MergeProposalFilter(bucket: MergeProposalFilter::BUCKET_IDP_ONLY), 'idp_id'),
        );
        $this->assertSame(
            [$aulaOnly],
            $this->column(new MergeProposalFilter(bucket: MergeProposalFilter::BUCKET_AULA_ONLY), 'local_id'),
        );
        // Either side's name is searched, since a reviewer knows one of them.
        $this->assertSame(['Ben Provider'], $this->column(new MergeProposalFilter(search: 'ben'), 'idp_name'));
        $this->assertSame(['Carla Aula'], $this->column(new MergeProposalFilter(search: ' carla '), 'local_name'));
        // An unknown bucket is no filter rather than an empty page.
        $this->assertSame(4, $this->list(new MergeProposalFilter(bucket: 'everything'))->total());
    }

    public function test_it_pages_and_caps_the_page_size(): void
    {
        foreach (['p1', 'p2', 'p3'] as $idpId) {
            $this->seedCandidate('user', $idpId, null);
        }

        $this->actAsAdmin();

        $second = $this->list(new MergeProposalFilter(page: 2, perPage: 2));

        $this->assertSame(3, $second->total());
        $this->assertSame(2, $second->currentPage());
        $this->assertSame(['p3'], array_column($second->items(), 'idp_id'));

        $this->assertSame(MergeProposalFilter::MAX_PER_PAGE, $this->list(new MergeProposalFilter(perPage: 999))->perPage());
        $this->assertSame(1, $this->list(new MergeProposalFilter(perPage: 0))->perPage());
        $this->assertSame(1, $this->list(new MergeProposalFilter(page: -5))->currentPage());
    }

    public function test_the_endpoint_pages_the_proposal_for_an_admin(): void
    {
        $userId = $this->seedUser('with_avatar', 20, realname: 'Full Name');
        $this->seedMedia($userId, 'face.png', systemType: 0);
        $this->seedCandidate('user', 'p-user', $userId, idpGroups: [['id' => 'g-room', 'name' => 'Room']]);
        $this->seedCandidate('room', 'g-room', 7);
        $roomId = $this->seedRoom('Klasse 5a');
        $this->seedMembership($userId, $roomId);

        $this->actAsAdmin();

        $this->getJson('/api/v2/auth/idp/merge-proposal?kind=user&per_page=10', $this->tenantHeaders())
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('per_page', 10)
            ->assertJsonPath('current_page', 1)
            ->assertJsonPath('data.0.idp_id', 'p-user')
            ->assertJsonPath('data.0.local_displayname', 'list_with_avatar')
            ->assertJsonPath('data.0.local_realname', 'Full Name')
            ->assertJsonPath('data.0.local_avatar', 'face.png')
            ->assertJsonPath('data.0.local_rooms', [['id' => $roomId, 'name' => 'Klasse 5a']])
            ->assertJsonPath('data.0.idp_groups', [['id' => 'g-room', 'name' => 'Room']]);
    }

    public function test_the_endpoint_refuses_a_non_admin(): void
    {
        $this->actAs($this->seedUser('pupil', 20));

        $this->getJson('/api/v2/auth/idp/merge-proposal', $this->tenantHeaders())
            ->assertStatus(403)
            ->assertJsonPath('error', 'admin_required');
    }

    // =========================================================
    // Helpers
    // =========================================================

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    private function list(MergeProposalFilter $filter): LengthAwarePaginator
    {
        return self::$testTenant->run(fn () => app(ListMergeProposalsUseCase::class)->execute($filter));
    }

    /**
     * @return list<mixed>
     */
    private function column(MergeProposalFilter $filter, string $column): array
    {
        return array_column($this->list($filter)->items(), $column);
    }

    private function seedCandidate(
        string $kind,
        ?string $idpId,
        ?int $localId,
        ?string $idpName = null,
        ?string $localName = null,
        ?array $idpGroups = null,
    ): int {
        return (int) self::$testTenant->run(fn () => DB::table('idp_merge_candidates')->insertGetId([
            'kind' => $kind,
            'idp_id' => $idpId,
            'idp_name' => $idpId === null ? null : ($idpName ?? 'Provider '.$idpId),
            'idp_name_kind' => $idpId === null ? null : 'real',
            'idp_groups' => $idpGroups === null ? null : json_encode($idpGroups),
            'local_id' => $localId,
            'local_name' => $localId === null ? null : ($localName ?? 'Aula row'),
            'outcome' => $idpId !== null && $localId !== null ? 'confident' : 'none',
            'decision' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]));
    }

    private function seedRoom(string $name, int $status = 1): int
    {
        return (int) self::$testTenant->run(fn () => DB::table('au_rooms')->insertGetId([
            'room_name' => $name,
            'status' => $status,
            'type' => 0,
            'hash_id' => self::PREFIX.md5($name.microtime(true)),
        ]));
    }

    private function seedMembership(int $userId, int $roomId): void
    {
        self::$testTenant->run(fn () => DB::table('au_rel_rooms_users')->insert([
            'room_id' => $roomId,
            'user_id' => $userId,
            'status' => 1,
            'created' => now(),
            'last_update' => now(),
        ]));
    }

    private function seedMedia(int $userId, string $filename, int $systemType): void
    {
        self::$testTenant->run(fn () => DB::table('au_media')->insert([
            'system_type' => $systemType,
            'updater_id' => $userId,
            'filename' => $filename,
            'created' => now(),
            'last_update' => now(),
        ]));
    }

    private function seedUser(string $name, int $level, ?string $realname = null): int
    {
        $username = self::PREFIX.$name;

        return (int) self::$testTenant->run(function () use ($username, $level, $realname) {
            LegacyUser::where('username', $username)->delete();

            $user = new LegacyUser;
            $user->username = $username;
            $user->displayname = $username;
            $user->realname = $realname;
            $user->pw = password_hash('secret', PASSWORD_BCRYPT);
            $user->status = UserStatus::Active;
            $user->userlevel = $level;
            $user->hash_id = md5($username.microtime(true));
            $user->save();

            return $user->id;
        });
    }

    private function actAsAdmin(): void
    {
        $this->actAs($this->seedUser('admin', 50));
    }

    private function actAs(int $userId): void
    {
        Passport::actingAs(self::$testTenant->run(fn () => LegacyUser::findOrFail($userId)));
    }

    /**
     * @return array<string, string>
     */
    private function tenantHeaders(): array
    {
        return ['aula-instance-code' => 'TEST001'];
    }

    private function clean(): void
    {
        self::$testTenant->run(function () {
            $ids = LegacyUser::where('username', 'like', self::PREFIX.'%')->pluck('id')->all();

            if ($ids !== []) {
                DB::table('au_media')->whereIn('updater_id', $ids)->delete();
                DB::table('au_rel_rooms_users')->whereIn('user_id', $ids)->delete();
                LegacyUser::whereIn('id', $ids)->delete();
            }

            DB::table('au_rooms')->where('hash_id', 'like', self::PREFIX.'%')->delete();
            DB::table('idp_merge_candidates')->truncate();
        });
    }
}

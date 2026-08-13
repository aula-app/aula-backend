<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Models\LegacyUser;
use App\Models\Tenant;
use App\Services\Idp\Migration\MergeProposalBuilder;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesTestTenant;
use Tests\TestCase;

/**
 * Matching a school's existing aula rows against its directory.
 *
 * The proposal decides who gets merged into whom, so the interesting cases are
 * the ones where it must refuse to decide.
 */
class IdpMergeProposalTest extends TestCase
{
    use CreatesTestTenant;

    private const string API_URL = 'https://api.eduplaces.test';

    private const string AUTH_URL = 'https://auth.eduplaces.test';

    private const string SCHOOL = 'school-proposal-test';

    /** @var list<array<string, mixed>> */
    private array $idmGroups = [];

    /** @var list<array<string, mixed>> */
    private array $idmUsers = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureTestTenantExists();
        self::$testTenant->refresh();
        self::$testTenant->update([
            'sso_provider' => 'eduplaces',
            'idp_school_id' => self::SCHOOL,
            'idp_migration_status' => Tenant::IDP_MIGRATION_CONNECTED,
        ]);

        config([
            'idp.providers.eduplaces.auth_url' => self::AUTH_URL,
            'idp.providers.eduplaces.api_url' => self::API_URL,
            'idp.providers.eduplaces.client_id' => 'test-client',
            'idp.providers.eduplaces.client_secret' => 'test-secret',
        ]);

        Cache::flush();
        $this->clean();

        $this->idmGroups = [];
        $this->idmUsers = [];
        $this->fakeDirectory();
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

    public function test_it_matches_the_same_person_written_two_ways(): void
    {
        $id = $this->seedAulaUser('Schüler1 aula');
        $this->idmUsers = [$this->person('p1', 'Schueler1', 'aula')];

        $this->build();

        $row = $this->candidateForIdp('p1');

        // Umlaut folding and casing are spelling, not identity.
        $this->assertSame(MergeProposalBuilder::OUTCOME_CONFIDENT, $row->outcome);
        $this->assertSame($id, (int) $row->local_id);
        $this->assertSame('merge', $row->decision);
    }

    public function test_it_refuses_to_choose_between_two_people_with_one_name(): void
    {
        $this->seedAulaUser('Max Müller');
        $this->seedAulaUser('Max Mueller');
        $this->idmUsers = [$this->person('p1', 'Max', 'Müller')];

        $this->build();

        $row = $this->candidateForIdp('p1');

        $this->assertSame(MergeProposalBuilder::OUTCOME_AMBIGUOUS, $row->outcome);
        // Never pre-selected: merging the wrong one hands over an account.
        $this->assertNull($row->decision);
    }

    public function test_two_provider_people_cannot_both_claim_one_aula_account(): void
    {
        $this->seedAulaUser('Max Müller');
        $this->idmUsers = [
            $this->person('p1', 'Max', 'Müller'),
            $this->person('p2', 'Max', 'Mueller'),
        ];

        $this->build();

        // Applying both would fold two people into a single account.
        $this->assertSame(MergeProposalBuilder::OUTCOME_AMBIGUOUS, $this->candidateForIdp('p1')->outcome);
        $this->assertSame(MergeProposalBuilder::OUTCOME_AMBIGUOUS, $this->candidateForIdp('p2')->outcome);
    }

    public function test_a_pseudonym_is_never_matched_and_says_so(): void
    {
        $this->seedAulaUser('Denk Raumfahrer');
        // What the directory returns when it will not disclose a real name.
        $this->idmUsers = [['id' => 'p1', 'role' => 'STUDENT', 'pseudonym' => 'Denk Raumfahrer', 'groups' => []]];

        $this->build();

        $row = $this->candidateForIdp('p1');

        $this->assertSame(MergeProposalBuilder::OUTCOME_NONE, $row->outcome);
        // The review has to be able to explain why this one cannot be matched.
        $this->assertSame(MergeProposalBuilder::NAME_PSEUDONYM, $row->idp_name_kind);
    }

    public function test_it_matches_on_the_name_a_person_is_called_by(): void
    {
        $id = $this->seedAulaUser('Johanna Becker');
        $this->idmUsers = [[
            'id' => 'p1', 'role' => 'STUDENT', 'groups' => [],
            'name' => ['firstFull' => 'Wilma Johanna Sophie', 'firstCall' => 'Johanna', 'last' => 'Becker'],
        ]];

        $this->build();

        $this->assertSame($id, (int) $this->candidateForIdp('p1')->local_id);
    }

    public function test_it_lists_people_who_exist_on_only_one_side(): void
    {
        $aulaOnly = $this->seedAulaUser('Nur In Aula');
        $this->idmUsers = [$this->person('p-new', 'Neue', 'Person')];

        $this->build();

        $newcomer = $this->candidateForIdp('p-new');
        $this->assertSame(MergeProposalBuilder::OUTCOME_NONE, $newcomer->outcome);
        $this->assertNull($newcomer->local_id);

        $stranded = DB::connection()->getName() ? $this->candidateForLocal($aulaOnly) : null;
        $this->assertNotNull($stranded, 'an aula-only account must appear in the proposal');
        $this->assertNull($stranded->idp_id);
    }

    public function test_it_matches_rooms_by_name_too(): void
    {
        $roomId = $this->seedAulaRoom('Klasse 5a');
        $this->idmGroups = [['id' => 'g1', 'name' => 'Klasse 5A']];

        $this->build();

        $row = $this->candidateForIdp('g1');

        $this->assertSame(MergeProposalBuilder::KIND_ROOM, $row->kind);
        $this->assertSame(MergeProposalBuilder::OUTCOME_CONFIDENT, $row->outcome);
        $this->assertSame($roomId, (int) $row->local_id);
    }

    public function test_it_ignores_accounts_that_are_already_linked(): void
    {
        // The admin who connected the school is settled; re-proposing them
        // would offer to merge them with themselves.
        self::$testTenant->run(function () {
            $user = new LegacyUser;
            $user->username = 'proposal.linked';
            $user->displayname = 'Schon Verknuepft';
            $user->realname = 'Schon Verknuepft';
            $user->idp_user_id = 'p-linked';
            $user->status = UserStatus::Active;
            $user->userlevel = 50;
            $user->hash_id = md5('proposal.linked');
            $user->save();
        });

        $this->idmUsers = [$this->person('p-linked', 'Schon', 'Verknuepft')];

        $this->build();

        $this->assertNull($this->candidateForIdp('p-linked')->local_id);
    }

    public function test_rebuilding_replaces_the_previous_proposal(): void
    {
        $this->seedAulaUser('Erste Runde');
        $this->idmUsers = [$this->person('p1', 'Erste', 'Runde')];
        $this->build();

        $this->idmUsers = [$this->person('p2', 'Zweite', 'Runde')];
        $this->build();

        // A stale proposal is worse than none: it would be applied unseen.
        $this->assertNull($this->findCandidate('p1'));
        $this->assertNotNull($this->findCandidate('p2'));
    }

    // =========================================================
    // Helpers
    // =========================================================

    private function build(): void
    {
        $tenant = self::$testTenant->fresh();
        $tenant->run(fn () => app(MergeProposalBuilder::class)->build($tenant));
    }

    /**
     * @return array<string, mixed>
     */
    private function person(string $id, string $first, string $last): array
    {
        return [
            'id' => $id, 'role' => 'STUDENT', 'groups' => [],
            'name' => ['firstFull' => $first, 'firstCall' => $first, 'last' => $last],
        ];
    }

    private function candidateForIdp(string $idpId): object
    {
        $row = $this->findCandidate($idpId);

        $this->assertNotNull($row, "no candidate row for {$idpId}");

        return $row;
    }

    private function findCandidate(string $idpId): ?object
    {
        return self::$testTenant->run(
            fn () => DB::table('idp_merge_candidates')->where('idp_id', $idpId)->first(),
        );
    }

    private function candidateForLocal(int $localId): ?object
    {
        return self::$testTenant->run(
            fn () => DB::table('idp_merge_candidates')->where('local_id', $localId)->whereNull('idp_id')->first(),
        );
    }

    private function seedAulaUser(string $realname): int
    {
        return (int) self::$testTenant->run(function () use ($realname) {
            $user = new LegacyUser;
            $user->username = 'proposal.'.md5($realname.microtime(true));
            $user->displayname = $realname;
            $user->realname = $realname;
            $user->status = UserStatus::Active;
            $user->userlevel = 20;
            $user->hash_id = md5($user->username);
            $user->save();

            return $user->id;
        });
    }

    private function seedAulaRoom(string $name): int
    {
        return (int) self::$testTenant->run(fn () => DB::table('au_rooms')->insertGetId([
            'room_name' => $name, 'status' => 1, 'type' => 0,
            'hash_id' => md5($name.microtime(true)),
        ]));
    }

    private function fakeDirectory(): void
    {
        Http::fake(function (Request $request) {
            $path = (string) parse_url($request->url(), PHP_URL_PATH);

            return match (true) {
                str_ends_with($path, '/oauth2/token') => Http::response([
                    'access_token' => 'token', 'token_type' => 'bearer', 'expires_in' => 3599,
                ]),
                (bool) preg_match('#/schools/[^/]+/groups$#', $path) => Http::response($this->idmGroups),
                (bool) preg_match('#/schools/[^/]+/(people|users)$#', $path) => Http::response($this->idmUsers),
                (bool) preg_match('#/groups/([^/]+)$#', $path, $m) => $this->groupDetail(urldecode($m[1])),
                default => Http::response(status: 404),
            };
        });
    }

    private function groupDetail(string $id): PromiseInterface
    {
        foreach ($this->idmGroups as $group) {
            if ($group['id'] === $id) {
                return Http::response($group + ['members' => []]);
            }
        }

        return Http::response(status: 404);
    }

    private function clean(): void
    {
        self::$testTenant->run(function () {
            $ids = LegacyUser::where('username', 'like', 'proposal.%')->pluck('id')->all();

            if ($ids !== []) {
                DB::table('au_rel_rooms_users')->whereIn('user_id', $ids)->delete();
                LegacyUser::whereIn('id', $ids)->delete();
            }

            DB::table('au_rooms')->whereIn('room_name', ['Klasse 5a', 'Klasse 5A'])->delete();
            DB::table('idp_merge_candidates')->truncate();
        });
    }
}

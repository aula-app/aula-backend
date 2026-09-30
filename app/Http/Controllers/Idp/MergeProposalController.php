<?php

declare(strict_types=1);

namespace App\Http\Controllers\Idp;

use App\Enums\UserLevel;
use App\Jobs\ImportSchoolForTenant;
use App\Models\IdpMergeCandidate;
use App\Models\LegacyUser;
use App\Models\Tenant;
use App\Services\Idp\Migration\MergeProposalApplier;
use App\Services\Idp\Migration\MergeProposalBuilder;
use App\Services\Idp\SchoolImport;
use App\UseCases\Idp\ListMergeProposalsUseCase;
use App\UseCases\Idp\MergeProposalFilter;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * The review an admin works through before a school's directory is imported
 * over its existing accounts.
 *
 * Every endpoint is admin-only: these decisions settle which account each
 * directory identity ends up on.
 */
class MergeProposalController extends Controller
{
    public function __construct(
        private readonly MergeProposalBuilder $builder,
        private readonly MergeProposalApplier $applier,
        private readonly ListMergeProposalsUseCase $lister,
    ) {}

    /**
     * Replace the proposal and move the tenant to IDP_MIGRATION_REVIEWING.
     */
    public function build(Request $request): JsonResponse
    {
        if ($denied = $this->denyNonAdmin($request)) {
            return $denied;
        }

        /** @var Tenant $tenant */
        $tenant = tenant();

        try {
            $counts = $this->builder->build($tenant);
        } catch (Throwable $e) {
            return response()->json(['error' => 'proposal_failed', 'detail' => $e->getMessage()], 422);
        }

        $tenant->update(['idp_migration_status' => Tenant::IDP_MIGRATION_REVIEWING]);

        return response()->json(['counts' => $counts]);
    }

    /**
     * A page of idp_merge_candidates, filtered by kind, search and bucket.
     */
    public function index(Request $request): JsonResponse
    {
        $filter = new MergeProposalFilter(
            kind: (string) $request->query('kind', ''),
            search: (string) $request->query('search', ''),
            bucket: (string) $request->query('bucket', ''),
            page: (int) $request->query('page', 1),
            perPage: (int) $request->query('per_page', MergeProposalFilter::DEFAULT_PER_PAGE),
        );

        try {
            $page = $this->lister->execute($filter);
        } catch (AuthorizationException) {
            return response()->json(['error' => 'admin_required'], 403);
        }

        return response()->json([
            'data' => $page->items(),
            'total' => $page->total(),
            'per_page' => $page->perPage(),
            'current_page' => $page->currentPage(),
        ]);
    }

    /**
     * Record the admin's decisions, including a local_id picked by hand.
     *
     * A hand-picked local_id is what makes a NAME_PSEUDONYM row matchable at
     * all, and what corrects a wrong pairing instead of only rejecting it.
     */
    public function decide(Request $request): JsonResponse
    {
        if ($denied = $this->denyNonAdmin($request)) {
            return $denied;
        }

        $data = $request->validate([
            'decisions' => 'required|array',
            'decisions.*.id' => 'required|integer',
            'decisions.*.decision' => 'nullable|in:merge,create',
            'decisions.*.local_id' => 'nullable|integer',
        ]);

        DB::transaction(function () use ($data): void {
            foreach ($data['decisions'] as $decision) {
                $this->recordDecision($decision);
            }
        });

        return response()->json(['success' => true]);
    }

    /**
     * Stamp the confirmed pairings and start the import.
     */
    public function apply(Request $request): JsonResponse
    {
        if ($denied = $this->denyNonAdmin($request)) {
            return $denied;
        }

        /** @var Tenant $tenant */
        $tenant = tenant();

        $problems = $this->applier->validate();

        if ($problems !== []) {
            // Nothing is stamped while any row is invalid: the admin fixes the
            // rows first.
            return response()->json(['error' => 'proposal_invalid', 'problems' => $problems], 422);
        }

        $applied = $this->applier->apply($tenant);

        $tenant->update([
            'idp_migration_status' => Tenant::IDP_MIGRATION_IMPORTING,
            'idp_import_status' => SchoolImport::STATUS_PENDING,
            'idp_import_error' => null,
            'idp_import_started_at' => now(),
            'idp_import_finished_at' => null,
        ]);

        ImportSchoolForTenant::dispatch($tenant->id);

        return response()->json(['applied' => $applied]);
    }

    /**
     * idp_migration_status with the linked, unlinked and signed-in account
     * counts behind it.
     */
    public function progress(Request $request): JsonResponse
    {
        if ($denied = $this->denyNonAdmin($request)) {
            return $denied;
        }

        /** @var Tenant $resolved */
        $resolved = tenant();

        // Read fresh, not from the resolved instance: tenancy caches it for the
        // request, and this endpoint is polled while ImportSchoolForTenant
        // writes exactly this column, so a cached copy reports IMPORTING for as
        // long as the polling lasts.
        $tenant = $resolved->fresh() ?? $resolved;

        return response()->json([
            'migration_status' => $tenant->idp_migration_status,
            'linked' => LegacyUser::whereNotNull('idp_user_id')->count(),
            'not_yet_linked' => LegacyUser::whereNull('idp_user_id')->count(),
            'signed_in_at_least_once' => LegacyUser::whereNotNull('sso_sub')->count(),
        ]);
    }

    /**
     * @param  array{id: int, decision?: ?string, local_id?: ?int}  $decision
     */
    private function recordDecision(array $decision): void
    {
        $row = IdpMergeCandidate::find($decision['id']);

        if ($row === null) {
            return;
        }

        $previous = $row->local_id;
        $row->decision = $decision['decision'] ?? null;

        if (array_key_exists('local_id', $decision)) {
            $row->local_id = $decision['local_id'];
            $row->local_name = $this->localName($row->kind, $decision['local_id']);
        }

        $row->save();

        if ($row->local_id !== $previous) {
            $this->releaseLocal($row->kind, $previous);
            $this->claimLocal($row->kind, $row->local_id);
        }
    }

    /**
     * Give an aula row no candidate references any more its aula-only row
     * back, or the review loses it and it cannot be paired again.
     */
    private function releaseLocal(string $kind, ?int $localId): void
    {
        if ($localId === null) {
            return;
        }

        if (IdpMergeCandidate::where('kind', $kind)->where('local_id', $localId)->exists()) {
            return;
        }

        IdpMergeCandidate::create([
            'kind' => $kind,
            'local_id' => $localId,
            'local_name' => $this->localName($kind, $localId),
            'outcome' => MergeProposalBuilder::OUTCOME_NONE,
        ]);
    }

    /**
     * Drop the aula-only row of an aula row a candidate now pairs with.
     */
    private function claimLocal(string $kind, ?int $localId): void
    {
        if ($localId === null) {
            return;
        }

        IdpMergeCandidate::where('kind', $kind)
            ->whereNull('idp_id')
            ->where('local_id', $localId)
            ->delete();
    }

    /**
     * The name MergeProposalBuilder records for the same aula row.
     */
    private function localName(string $kind, ?int $localId): ?string
    {
        if ($localId === null) {
            return null;
        }

        // au_rooms has no model.
        if ($kind === MergeProposalBuilder::KIND_ROOM) {
            return DB::table('au_rooms')->where('id', $localId)->value('room_name');
        }

        $user = LegacyUser::find($localId, ['realname', 'displayname']);

        return $user === null ? null : (string) ($user->realname ?: $user->displayname);
    }

    private function denyNonAdmin(Request $request): ?JsonResponse
    {
        /** @var LegacyUser|null $user */
        $user = Auth::user();

        if (($user?->userlevel?->value ?? 0) < UserLevel::Admin->value) {
            return response()->json(['error' => 'admin_required'], 403);
        }

        return null;
    }
}

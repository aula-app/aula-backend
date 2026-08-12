<?php

declare(strict_types=1);

namespace App\Http\Controllers\Idp;

use App\Enums\UserLevel;
use App\Http\Controllers\Controller;
use App\Jobs\ImportSchoolForTenant;
use App\Models\LegacyUser;
use App\Models\Tenant;
use App\Services\Idp\Migration\MergeProposalApplier;
use App\Services\Idp\Migration\MergeProposalBuilder;
use App\Services\Idp\SchoolImport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * The review an admin works through before a school's directory is imported
 * over its existing accounts.
 *
 * Everything here is admin-only: these decisions determine who ends up owning
 * which account.
 */
class MergeProposalController extends Controller
{
    public function __construct(
        private readonly MergeProposalBuilder $builder,
        private readonly MergeProposalApplier $applier,
    ) {}

    /**
     * Build a fresh proposal, discarding any earlier one.
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
     * The proposal, in the three buckets the review shows.
     */
    public function index(Request $request): JsonResponse
    {
        if ($denied = $this->denyNonAdmin($request)) {
            return $denied;
        }

        $query = DB::table('idp_merge_candidates');

        if (($kind = (string) $request->query('kind', '')) !== '') {
            $query->where('kind', $kind);
        }

        // A thousand-pupil school makes an unfiltered list useless, so the
        // review is expected to search rather than scroll.
        if (($search = trim((string) $request->query('search', ''))) !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('local_name', 'like', "%{$search}%")
                    ->orWhere('idp_name', 'like', "%{$search}%");
            });
        }

        if (($bucket = (string) $request->query('bucket', '')) !== '') {
            match ($bucket) {
                'merges' => $query->whereNotNull('idp_id')->whereNotNull('local_id'),
                'idp_only' => $query->whereNotNull('idp_id')->whereNull('local_id'),
                'aula_only' => $query->whereNull('idp_id')->whereNotNull('local_id'),
                default => null,
            };
        }

        $page = $query->orderBy('kind')->orderBy('outcome')->orderBy('id')
            ->paginate(perPage: min((int) $request->query('per_page', 50), 200));

        return response()->json([
            'data' => $page->items(),
            'total' => $page->total(),
            'per_page' => $page->perPage(),
            'current_page' => $page->currentPage(),
        ]);
    }

    /**
     * Record what the admin decided, including any target they picked by hand.
     *
     * The manual target is what makes a pseudonym-only person matchable at all,
     * and what lets a wrong guess be corrected rather than merely rejected.
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

        foreach ($data['decisions'] as $decision) {
            $update = ['decision' => $decision['decision'] ?? null, 'updated_at' => now()];

            if (array_key_exists('local_id', $decision)) {
                $update['local_id'] = $decision['local_id'];
                $update['local_name'] = $this->localName($decision['id'], $decision['local_id']);
            }

            DB::table('idp_merge_candidates')->where('id', $decision['id'])->update($update);
        }

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
            // Better to send the admin back to the rows than to apply a
            // proposal that is partly wrong.
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
     * How far the school has got: the middle number is the one that says
     * whether the migration is finished.
     */
    public function progress(Request $request): JsonResponse
    {
        if ($denied = $this->denyNonAdmin($request)) {
            return $denied;
        }

        /** @var Tenant $resolved */
        $resolved = tenant();

        // Read through, not from the resolved instance: tenancy caches it for
        // the request, and this endpoint is polled while a queued import is
        // changing exactly this column. A cached copy reports `importing`
        // forever, however long the import has been finished.
        $tenant = $resolved->fresh() ?? $resolved;

        return response()->json([
            'migration_status' => $tenant->idp_migration_status,
            'linked' => LegacyUser::whereNotNull('idp_user_id')->count(),
            'not_yet_linked' => LegacyUser::whereNull('idp_user_id')->count(),
            'signed_in_at_least_once' => LegacyUser::whereNotNull('sso_sub')->count(),
        ]);
    }

    private function localName(int $candidateId, ?int $localId): ?string
    {
        if ($localId === null) {
            return null;
        }

        $kind = DB::table('idp_merge_candidates')->where('id', $candidateId)->value('kind');

        return $kind === MergeProposalBuilder::KIND_ROOM
            ? DB::table('au_rooms')->where('id', $localId)->value('room_name')
            : DB::table('au_users_basedata')->where('id', $localId)->value('displayname');
    }

    private function denyNonAdmin(Request $request): ?JsonResponse
    {
        /** @var LegacyUser|null $user */
        $user = $request->attributes->get('authenticated_user');

        if (($user?->userlevel?->value ?? 0) < UserLevel::Admin->value) {
            return response()->json(['error' => 'admin_required'], 403);
        }

        return null;
    }
}

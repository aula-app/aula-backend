<?php

declare(strict_types=1);

namespace App\Http\Controllers\Idp;

use App\Http\Controllers\Controller;
use App\Models\LegacyUser;
use App\Models\Tenant;
use App\Services\Idp\SchoolImport;
use Illuminate\Http\JsonResponse;

/**
 * Reports how the initial school import is going.
 *
 * SsoController::bootstrapIdpTenant() dispatches ImportSchoolForTenant on the
 * first SSO login, and every later login is held out until that finishes, or it
 * would reach a school with half its rooms and none of its classmates. The
 * frontend polls this and keeps a setup screen up while `ready` is false.
 */
class ImportStatusController extends Controller
{
    public function show(): JsonResponse
    {
        /** @var Tenant $resolved */
        $resolved = tenant();

        // Read fresh, not from the resolved instance: tenancy caches it for the
        // request, and this endpoint is polled while ImportSchoolForTenant
        // writes exactly these columns, so a cached copy keeps reporting the
        // status from before the import started.
        $tenant = $resolved->fresh() ?? $resolved;

        $status = $tenant->idp_import_status;

        return response()->json([
            'ready' => $this->isReady($tenant, $status),
            'provider' => $tenant->sso_provider,
            'status' => $status,
            'rooms' => (int) $tenant->idp_import_rooms,
            'users' => (int) $tenant->idp_import_users,
            'error' => $tenant->idp_import_error,
            'started_at' => $tenant->idp_import_started_at,
            'finished_at' => $tenant->idp_import_finished_at,
        ]);
    }

    /**
     * Whether the school is usable, or its logins wait for an import.
     */
    private function isReady(Tenant $tenant, ?string $status): bool
    {
        // A tenant that syncs from no directory has no import to wait for.
        if (! $tenant->usesIdpDirectory()) {
            return true;
        }

        if ($status === SchoolImport::STATUS_COMPLETED) {
            return true;
        }

        // A tenant already running on aula stays open through its migration:
        // a setup screen would lock its users out of a working school. Only the
        // import MergeProposalController::apply() starts is worth waiting for.
        if ($tenant->isMigratingToIdp()) {
            return ! in_array($status, [SchoolImport::STATUS_PENDING, SchoolImport::STATUS_RUNNING], true);
        }

        // A null status means the first login has not happened yet, which is
        // worth waiting for, or that bootstrapIdpTenant() declined.
        if ($status === null) {
            return $this->bootstrapAlreadyDeclined();
        }

        // A tenant with no prior aula use is blocked until the import finishes.
        return false;
    }

    /**
     * Whether an SSO login has already happened without starting an import.
     *
     * bootstrapIdpTenant() is the only caller that starts one, and it writes
     * STATUS_PENDING before dispatching, so an import in flight already has a
     * status. It guards itself on `sso_sub` too, so once one exists no later
     * login retries.
     */
    private function bootstrapAlreadyDeclined(): bool
    {
        return LegacyUser::whereNotNull('sso_sub')->exists();
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Idp;

use App\Models\LegacyUser;
use App\Models\Tenant;
use App\Services\Idp\SchoolImport;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

/**
 * Reports how the initial school import is going.
 *
 * The first SSO login pulls the whole school in synchronously. Everyone else
 * has to be held out of aula until that finishes, or they would arrive at a
 * school with half its rooms and none of its classmates. The frontend polls
 * this and keeps the user on a setup screen while `ready` is false.
 */
class ImportStatusController extends Controller
{
    public function show(): JsonResponse
    {
        /** @var Tenant $resolved */
        $resolved = tenant();

        // Read through, not from the resolved instance: tenancy caches it for
        // the request, and this endpoint exists to be polled while another
        // process is changing exactly these columns. A cached copy would report
        // the status from before the import started, forever.
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
     * Whether the school is usable, or its users have to wait for an import.
     */
    private function isReady(Tenant $tenant, ?string $status): bool
    {
        // Tenants that sync from no directory have no import to wait for.
        if (! $tenant->usesIdpDirectory()) {
            return true;
        }

        if ($status === SchoolImport::STATUS_COMPLETED) {
            return true;
        }

        // A school that already ran on aula stays open throughout its
        // migration: it is a working school, and holding its users on a setup
        // screen would lock them out of it. Only the import that applying the
        // merge starts is worth waiting for.
        if ($tenant->isMigratingToIdp()) {
            return ! in_array($status, [SchoolImport::STATUS_PENDING, SchoolImport::STATUS_RUNNING], true);
        }

        // No status means either the first login has not happened yet, which is
        // worth waiting for, or that it happened and declined to bootstrap.
        if ($status === null) {
            return $this->bootstrapAlreadyDeclined();
        }

        // Greenfield: blocked until the import has finished.
        return false;
    }

    /**
     * Whether the first SSO login has happened without starting an import.
     *
     * It is the only thing that starts one, and it marks the tenant pending
     * before dispatching, so anything in flight already has a status. An
     * `sso_sub` is the same signal bootstrapIdpTenant() guards itself with:
     * once one exists no login will retry, so nothing is coming.
     */
    private function bootstrapAlreadyDeclined(): bool
    {
        return LegacyUser::whereNotNull('sso_sub')->exists();
    }
}

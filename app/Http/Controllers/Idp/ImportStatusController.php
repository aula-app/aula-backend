<?php

declare(strict_types=1);

namespace App\Http\Controllers\Idp;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\Idp\SchoolImport;
use Illuminate\Http\JsonResponse;

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
            // Tenants that sync from no directory have no import and are never
            // blocked. A synced tenant is blocked until its import has
            // finished — including before the first login, when the school is
            // still unknown and the import has not begun.
            'ready' => ! $tenant->usesIdpDirectory()
                || $status === SchoolImport::STATUS_COMPLETED,
            'provider' => $tenant->sso_provider,
            'status' => $status,
            'rooms' => (int) $tenant->idp_import_rooms,
            'users' => (int) $tenant->idp_import_users,
            'error' => $tenant->idp_import_error,
            'started_at' => $tenant->idp_import_started_at,
            'finished_at' => $tenant->idp_import_finished_at,
        ]);
    }
}

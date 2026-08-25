<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Marks a school that already used aula before it started syncing from a
     * directory.
     *
     * On such a tenant SsoController::bootstrapIdpTenant() declines and
     * accounts are matched or linked rather than created. Null means a tenant
     * with no prior aula use, which bootstraps on its first SSO login.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (! Schema::hasColumn('tenants', 'idp_migration_status')) {
                $table->string('idp_migration_status', 16)->nullable()->after('idp_school_id')
                    ->comment('null, flagged, connected, reviewing, importing, linking, completed');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (Schema::hasColumn('tenants', 'idp_migration_status')) {
                $table->dropColumn('idp_migration_status');
            }
        });
    }
};

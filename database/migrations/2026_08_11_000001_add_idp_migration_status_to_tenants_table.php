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
     * Such a tenant behaves differently from a greenfield one: the first SSO
     * login must not bootstrap it, and users are matched or linked rather than
     * simply created. Null means the greenfield rules apply.
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

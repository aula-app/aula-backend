<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `eduplaces_school_id` names one vendor, while a school's directory id is
     * the same concept for every provider aula brokers, so the column loses the
     * vendor name. `tenants.sso_provider` says which provider it came from.
     *
     * A rename rather than a new column: tenants already hold values here, and
     * SsoController::resolveTenantFromEduplacesClaim() resolves against them.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('tenants', 'eduplaces_school_id') || Schema::hasColumn('tenants', 'idp_school_id')) {
            return;
        }

        // The unique index is dropped and recreated around the rename, rather
        // than relying on every driver to carry it across with the column.
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropUnique(['eduplaces_school_id']);
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->renameColumn('eduplaces_school_id', 'idp_school_id');
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->unique('idp_school_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('tenants', 'idp_school_id') || Schema::hasColumn('tenants', 'eduplaces_school_id')) {
            return;
        }

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropUnique(['idp_school_id']);
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->renameColumn('idp_school_id', 'eduplaces_school_id');
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->unique('eduplaces_school_id');
        });
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `eduplaces_school_id` predates the directory sync and names one vendor.
     *
     * aula brokers several identity providers, and a school's directory id is
     * the same concept whichever one it came from, so the column loses the
     * vendor name. Which provider a tenant syncs from is `sso_provider`.
     *
     * A rename rather than a new column: tenants already carry values here and
     * the IdP-initiated login resolves against them.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('tenants', 'eduplaces_school_id') || Schema::hasColumn('tenants', 'idp_school_id')) {
            return;
        }

        // Drop and recreate the unique index around the rename so the change is
        // driver-agnostic rather than relying on the index following the column.
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

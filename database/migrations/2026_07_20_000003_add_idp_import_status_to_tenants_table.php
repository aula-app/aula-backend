<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The first SSO login on a directory-synced tenant pulls the whole school in
     * before anyone can use aula. The frontend polls this state to know whether
     * to hold the user on a "setting up your school" screen.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (! Schema::hasColumn('tenants', 'idp_import_status')) {
                $table->string('idp_import_status', 16)->nullable()->after('idp_school_id')
                    ->comment('null = never run, running, completed, failed');
            }
            if (! Schema::hasColumn('tenants', 'idp_import_rooms')) {
                $table->unsignedInteger('idp_import_rooms')->default(0)->after('idp_import_status');
            }
            if (! Schema::hasColumn('tenants', 'idp_import_users')) {
                $table->unsignedInteger('idp_import_users')->default(0)->after('idp_import_rooms');
            }
            if (! Schema::hasColumn('tenants', 'idp_import_error')) {
                $table->text('idp_import_error')->nullable()->after('idp_import_users');
            }
            if (! Schema::hasColumn('tenants', 'idp_import_started_at')) {
                $table->dateTime('idp_import_started_at')->nullable()->after('idp_import_error');
            }
            if (! Schema::hasColumn('tenants', 'idp_import_finished_at')) {
                $table->dateTime('idp_import_finished_at')->nullable()->after('idp_import_started_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(array_filter([
                'idp_import_status',
                'idp_import_rooms',
                'idp_import_users',
                'idp_import_error',
                'idp_import_started_at',
                'idp_import_finished_at',
            ], fn (string $col) => Schema::hasColumn('tenants', $col)));
        });
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->boolean('sso_force_logout')->default(true)->after('sso_idp_config')
                ->comment('When true, logging out redirects the user to Keycloak to end their SSO session');
        });

        DB::table('tenants')->whereNull('sso_force_logout')->update(['sso_force_logout' => true]);
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('sso_force_logout');
        });
    }
};

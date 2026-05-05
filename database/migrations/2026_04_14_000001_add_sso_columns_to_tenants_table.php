<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (! Schema::hasColumn('tenants', 'sso_enabled')) {
                $table->boolean('sso_enabled')->default(false)->after('jwt_key');
            }
            if (! Schema::hasColumn('tenants', 'sso_provider')) {
                $table->string('sso_provider', 64)->nullable()->after('sso_enabled')
                    ->comment('IdP alias in Keycloak, e.g. mock-iserv, vidis, iserv');
            }
            if (! Schema::hasColumn('tenants', 'sso_idp_config')) {
                $table->json('sso_idp_config')->nullable()->after('sso_provider')
                    ->comment('Per-tenant IdP config (IServ URL, client_id, etc.)');
            }
            if (! Schema::hasColumn('tenants', 'sso_force_logout')) {
                $table->boolean('sso_force_logout')->default(true)->after('sso_idp_config')
                    ->comment('When true, logging out redirects the user to Keycloak to end their SSO session');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(array_filter(
                ['sso_enabled', 'sso_provider', 'sso_idp_config', 'sso_force_logout'],
                fn (string $col) => Schema::hasColumn('tenants', $col),
            ));
        });
    }
};

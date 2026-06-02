<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('au_users_basedata', function (Blueprint $table) {
            if (! Schema::hasColumn('au_users_basedata', 'sso_sub')) {
                $table->string('sso_sub', 255)->nullable()->unique()->after('email')
                    ->comment('Subject identifier from the SSO identity provider (OIDC sub claim)');
            }
            if (! Schema::hasColumn('au_users_basedata', 'sso_provider')) {
                $table->string('sso_provider', 100)->nullable()->after('sso_sub')
                    ->comment('SSO identity provider name used to create this user (e.g. mock-iserv, vidis)');
            }
            if (! Schema::hasColumn('au_users_basedata', 'sso_id_token')) {
                $table->text('sso_id_token')->nullable()->after('sso_provider')
                    ->comment('OIDC id_token from Keycloak; used for RP-initiated logout (id_token_hint)');
            }
            if (! Schema::hasColumn('au_users_basedata', 'sso_refresh_token')) {
                $table->text('sso_refresh_token')->nullable()->after('sso_id_token')
                    ->comment('Keycloak refresh token; used for server-side session revocation on logout');
            }
            if (! Schema::hasColumn('au_users_basedata', 'sso_idp_id_token')) {
                $table->text('sso_idp_id_token')->nullable()->after('sso_refresh_token')
                    ->comment('id_token from the upstream IdP (e.g. mock-iserv); used to logout from the IdP session');
            }
        });
    }

    public function down(): void
    {
        Schema::table('au_users_basedata', function (Blueprint $table) {
            $table->dropColumn(array_filter(
                ['sso_sub', 'sso_provider', 'sso_id_token', 'sso_refresh_token', 'sso_idp_id_token'],
                fn (string $col) => Schema::hasColumn('au_users_basedata', $col),
            ));
        });
    }
};

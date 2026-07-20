<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `sso_sub` holds the Keycloak subject, which Keycloak mints itself when it
     * brokers an upstream provider. Directory imports and webhooks reference
     * the provider's own user id instead, so it needs its own column to match
     * against. Which provider it belongs to is a tenant property.
     */
    public function up(): void
    {
        Schema::table('au_users_basedata', function (Blueprint $table) {
            if (! Schema::hasColumn('au_users_basedata', 'idp_user_id')) {
                $table->string('idp_user_id', 64)->nullable()->unique()->after('sso_sub')
                    ->comment('identity provider user id; the sub of the upstream id_token');
            }
        });
    }

    public function down(): void
    {
        Schema::table('au_users_basedata', function (Blueprint $table) {
            if (Schema::hasColumn('au_users_basedata', 'idp_user_id')) {
                $table->dropUnique(['idp_user_id']);
                $table->dropColumn('idp_user_id');
            }
        });
    }
};

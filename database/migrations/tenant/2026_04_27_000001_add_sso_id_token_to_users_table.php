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
            $table->text('sso_id_token')->nullable()->after('sso_provider')
                ->comment('OIDC id_token from Keycloak; used for RP-initiated logout (id_token_hint)');
        });
    }

    public function down(): void
    {
        Schema::table('au_users_basedata', function (Blueprint $table) {
            $table->dropColumn('sso_id_token');
        });
    }
};

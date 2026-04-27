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
            $table->text('sso_refresh_token')->nullable()->after('sso_id_token')
                ->comment('Keycloak refresh token; used for server-side session revocation on logout');
        });
    }

    public function down(): void
    {
        Schema::table('au_users_basedata', function (Blueprint $table) {
            $table->dropColumn('sso_refresh_token');
        });
    }
};

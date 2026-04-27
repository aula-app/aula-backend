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
            $table->text('sso_idp_id_token')->nullable()->after('sso_refresh_token')
                ->comment('id_token from the upstream IdP (e.g. mock-iserv); used to logout from the IdP session');
        });
    }

    public function down(): void
    {
        Schema::table('au_users_basedata', function (Blueprint $table) {
            $table->dropColumn('sso_idp_id_token');
        });
    }
};

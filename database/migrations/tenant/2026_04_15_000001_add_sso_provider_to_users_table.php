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
            $table->string('sso_provider', 100)->nullable()->after('sso_sub')
                ->comment('SSO identity provider name used to create this user (e.g. mock-iserv, vidis)');
        });
    }

    public function down(): void
    {
        Schema::table('au_users_basedata', function (Blueprint $table) {
            $table->dropColumn('sso_provider');
        });
    }
};

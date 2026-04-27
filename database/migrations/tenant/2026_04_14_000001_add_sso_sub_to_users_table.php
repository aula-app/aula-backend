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
            $table->string('sso_sub', 255)->nullable()->unique()->after('email')
                ->comment('Subject identifier from the SSO identity provider (OIDC sub claim)');
        });
    }

    public function down(): void
    {
        Schema::table('au_users_basedata', function (Blueprint $table) {
            $table->dropColumn('sso_sub');
        });
    }
};

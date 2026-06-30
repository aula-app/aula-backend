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
            if (Schema::hasColumn('au_users_basedata', 'sso_provider')) {
                $table->dropColumn('sso_provider');
            }
        });
    }

    public function down(): void
    {
        Schema::table('au_users_basedata', function (Blueprint $table) {
            if (! Schema::hasColumn('au_users_basedata', 'sso_provider')) {
                $table->string('sso_provider', 100)->nullable()->after('sso_sub')
                    ->comment('SSO identity provider name used to create this user (e.g. mock-iserv, vidis)');
            }
        });
    }
};

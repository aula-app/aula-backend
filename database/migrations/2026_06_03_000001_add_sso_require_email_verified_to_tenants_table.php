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
            if (! Schema::hasColumn('tenants', 'sso_require_email_verified')) {
                $table->boolean('sso_require_email_verified')->default(true)->after('sso_required')
                    ->comment('When true, reject SSO logins whose id_token does not assert email_verified=true. Default true (strict); flip off per-tenant when the IdP is trusted to control all email addresses.');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (Schema::hasColumn('tenants', 'sso_require_email_verified')) {
                $table->dropColumn('sso_require_email_verified');
            }
        });
    }
};

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
            if (! Schema::hasColumn('tenants', 'sso_required')) {
                $table->boolean('sso_required')->default(false)->after('sso_force_logout')
                    ->comment('When true, password login is refused for all users in this tenant — SSO-only');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (Schema::hasColumn('tenants', 'sso_required')) {
                $table->dropColumn('sso_required');
            }
        });
    }
};

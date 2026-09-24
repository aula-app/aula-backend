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
            if (! Schema::hasColumn('tenants', 'eduplaces_school_id')) {
                $table->string('eduplaces_school_id', 64)->nullable()->unique()->after('sso_idp_config')
                    ->comment('Eduplaces school UUID, used to map IdP-initiated logins to the right tenant');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (Schema::hasColumn('tenants', 'eduplaces_school_id')) {
                $table->dropUnique(['eduplaces_school_id']);
                $table->dropColumn('eduplaces_school_id');
            }
        });
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * IdP groups of a user row, stored at build time.
     */
    public function up(): void
    {
        Schema::table('idp_merge_candidates', function (Blueprint $table) {
            if (! Schema::hasColumn('idp_merge_candidates', 'idp_groups')) {
                $table->json('idp_groups')->nullable()->after('idp_name_kind')
                    ->comment('[{id, name}] of the directory user; null on room and aula-only rows');
            }
        });
    }

    public function down(): void
    {
        Schema::table('idp_merge_candidates', function (Blueprint $table) {
            if (Schema::hasColumn('idp_merge_candidates', 'idp_groups')) {
                $table->dropColumn('idp_groups');
            }
        });
    }
};

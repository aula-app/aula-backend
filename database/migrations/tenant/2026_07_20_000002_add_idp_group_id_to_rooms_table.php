<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A provider group ("Klasse 5a") becomes an `au_rooms` row, not an
     * `au_groups` row: participation happens in rooms, and room membership
     * carries the per-room role.
     */
    public function up(): void
    {
        Schema::table('au_rooms', function (Blueprint $table) {
            if (! Schema::hasColumn('au_rooms', 'idp_group_id')) {
                $table->string('idp_group_id', 64)->nullable()->unique()->after('hash_id')
                    ->comment('identity provider group id for rooms synced from a directory');
            }
        });
    }

    public function down(): void
    {
        Schema::table('au_rooms', function (Blueprint $table) {
            if (Schema::hasColumn('au_rooms', 'idp_group_id')) {
                $table->dropUnique(['idp_group_id']);
                $table->dropColumn('idp_group_id');
            }
        });
    }
};

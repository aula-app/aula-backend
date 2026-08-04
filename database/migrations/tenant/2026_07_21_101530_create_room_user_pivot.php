<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('au_rel_rooms_users', function (Blueprint $table) {
            if (! Schema::hasColumn('au_rel_rooms_users', 'room_user_level')) {
                $table->integer('room_user_level');
            }
            // TODO foreign restrictions?
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        /* Schema::dropIfExists('room_user'); */
        Schema::table('au_rel_rooms_users', function (Blueprint $table) {
            $table->dropColumn('room_user_level');
        });
    }
};

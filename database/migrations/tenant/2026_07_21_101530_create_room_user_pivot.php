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
        $created = false;
        Schema::table('au_rel_rooms_users', function (Blueprint $table) use (&$created) {
            if (! Schema::hasColumn('au_rel_rooms_users', 'room_user_level')) {
                $table->integer('room_user_level');
                $created = true;
            }
            // TODO foreign restrictions?
        });
        if ($created) {
            $allUserRoles = DB::table('au_users_basedata')->pluck('roles', 'id')->all();
            // "join" map hash_id => id
            $roomIds = DB::table('au_rooms')->pluck('id', 'hash_id')->all();
            foreach ($allUserRoles as $userId => $userRoles) {
                $userRoles = json_decode($userRoles);
                foreach ($userRoles as $userRole) {
                    $affected = DB::table('au_rel_rooms_users')
                        ->where([
                            'user_id' => $userId,
                            'room_id' => $roomIds[$userRole->room],
                        ])
                        ->update(['room_user_level' => $userRole->role]);
                    echo "updated '$affected' au_rel_rooms_users for user '$userId' room '{$roomIds[$userRole->room]}' with role '{$userRole->role}'\n";
                    /*
                    // TODO how to detect failure? sole() won't cut it...
                    if ($affected !== 1) {
                        throw new RuntimeException("could not update au_rel_rooms_users for user '$userId' room '{$roomIds[$userRole->room]}' with role '{$userRole->role}'");
                    }
                    */
                }
            }
        }
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

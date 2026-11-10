<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $created = false;
        Schema::table('au_rel_rooms_users', function (Blueprint $table) use (&$created) {
            if (! Schema::hasColumn('au_rel_rooms_users', 'room_member_role')) {
                $table->integer('room_member_role')->default(0);
                $created = true;
            }
            // TODO foreign restrictions?
        });
        if ($created) {
            $allUserRoles = DB::table('au_users_basedata')->pluck('roles', 'id')->all();
            $userCount = count($allUserRoles);
            // "join" map hash_id => id
            $roomIds = DB::table('au_rooms')->pluck('id', 'hash_id')->all();
            $roomCount = count($roomIds);
            $totalAffected = 0;
            foreach ($allUserRoles as $userId => $userRoles) {
                $userRoles = json_decode($userRoles);
                foreach ($userRoles as $userRole) {
                    if (!array_key_exists($userRole->room, $roomIds)) {
                        $message = "Can not update au_rel_rooms_users relation for user '$userId', room '{$userRole->room}' not found";
                        Log::warning($message, [
                            'tenant' => tenant('instance_code'),
                            'user' => $userId,
                            'roomId' => $userRole->room,
                            'roles' => $userRoles
                        ]);
                        continue;
                    }
                    $affected = DB::table('au_rel_rooms_users')
                        ->where([
                            'user_id' => $userId,
                            'room_id' => $roomIds[$userRole->room],
                        ])
                        ->update(['room_member_role' => $userRole->role]);
                    $totalAffected += $affected;
                    $message = "Updated [{$affected}] au_rel_rooms_users for user '$userId' room '{$roomIds[$userRole->room]}' with role '{$userRole->role}'\n";
                    Log::debug($message, ['tenant' => tenant('instance_code'), 'count' => $affected]);
                }
            }
            $message = "Updated [{$totalAffected}] au_rel_rooms_users relations for [{$userCount}] users across [{$roomCount}] rooms";
            Log::info($message, [
                'tenant' => tenant('instance_code'),
                'userCount' => $userCount,
                'roomCount' => $roomCount,
                'totalAffected' => $totalAffected
            ]);
            DB::table('au_systemlog')->insert(['message' => $message]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        /* Schema::dropIfExists('room_user'); */
        Schema::table('au_rel_rooms_users', function (Blueprint $table) {
            $table->dropColumn('room_member_role');
        });
    }
};

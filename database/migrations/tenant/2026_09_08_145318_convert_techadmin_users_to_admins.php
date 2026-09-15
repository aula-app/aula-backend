<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class () extends Migration {
    public function up(): void
    {
        $users = DB::table('au_users_basedata')
            ->where('userlevel', 60)
            ->get(['id', 'username']);
        $numOfUsers = $users->count();
        $message = "Converting [{$numOfUsers}] users from 'techadmin' userlevel to 'admin' userlevel: " . $users->toJson();
        Log::info($message, [ 'tenant' => tenant('instance_code'), 'count' => $numOfUsers ]);

        DB::table('au_systemlog')->insert([ 'message' => $message ]);
        $users = DB::table('au_users_basedata')
            ->where('userlevel', 60)
            ->update(['refresh_token' => 1, 'userlevel' => 50]);
    }

    public function down(): void
    {
        // irreversible, destructive
    }
};

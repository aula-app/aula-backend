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
        $message = "Migrating [{$numOfUsers}] from techadmin to admin userlevel: " . $users->toJson();
        Log::info($message, [ 'tenant' => tenant('instance_code'), 'count' => $numOfUsers ]);

        DB::table('au_systemlog')->insert([ 'message' => $message ]);
        DB::statement('UPDATE au_users_basedata SET userlevel=50, refresh_token=1 WHERE userlevel=60');
    }

    public function down(): void
    {
        // irreversible, destructive
    }
};

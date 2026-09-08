<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('UPDATE au_users_basedata SET userlevel=50, refresh_token=1 WHERE userlevel=60');
    }

    public function down(): void
    {
        // irreversible, destructive
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (! Schema::hasColumn('tenants', 'allow_search_by_name')) {
                $table->boolean('allow_search_by_name')
                    ->default(false)
                    ->after('instance_code')
                    ->comment('Name of the school will be available in auto-complete search. If false, only the instance code will be accepted.');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (Schema::hasColumn('tenants', 'allow_search_by_name')) {
                $table->dropColumn('allow_search_by_name');
            }
        });
    }
};

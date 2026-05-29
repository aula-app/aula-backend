<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255)->unique();
            $table->timestamps();
        });

        DB::table('school_types')->insert(array_map(
            fn (string $name) => ['name' => $name, 'created_at' => now(), 'updated_at' => now()],
            [
                'Grundschule',
                'Förderschule',
                'Hauptschule',
                'Realschule',
                'Gymnasium',
                'Integrierte Gesamtschule',
                'Fachoberschule',
                'Berufsoberschule',
                'Berufsfachschule',
                'Berufsschule',
                'Fachgymnasium',
            ]
        ));

        Schema::table('tenants', function (Blueprint $table) {
            $table->foreignId('school_type_id')->nullable()->after('contact_info')
                ->constrained('school_types')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('school_type_id');
        });

        Schema::dropIfExists('school_types');
    }
};

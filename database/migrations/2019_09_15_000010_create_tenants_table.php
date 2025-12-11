<?php

declare(strict_types=1);

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
        Schema::create('tenants', function (Blueprint $table) {
            // use mariadb-specific UUID_v4() function to initialize id field
            $table->uuid('id')->primary()->default(DB::raw('UUID_v4()'));

            $table->string('name', 255)->nullable(false)->unique();
            $table->string('api_base_url', 255)->nullable(false)->default('https://neu.aula.de');
            $table->string('contact_info', 255)->nullable();

            $table->string('admin1_name', 255)->nullable();
            $table->string('admin1_username', 255);
            $table->string('admin1_email', 255);
            $table->string('admin1_init_pass_url')->nullable();

            // admin2 can have all fields nullable
            $table->string('admin2_name', 255)->nullable();
            $table->string('admin2_username', 255)->nullable();
            $table->string('admin2_email', 255)->nullable();
            $table->string('admin2_init_pass_url')->nullable();

            $table->string('instance_code', 10)->unique();
            $table->string('jwt_key')->nullable();

            $table->timestamps();
            $table->json('data')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};

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
            $table->string('id')->primary();

            // your custom columns may go here

            $table->string('name', 255)->nullable(false)->unique();
            $table->string('api_base_url', 255)->nullable(false);
            $table->string('contact_info', 255)->nullable();

            $table->string('admin_name', 255)->nullable();
            $table->string('admin_username', 255)->nullable();
            $table->string('admin_email', 255);
            $table->string('admin_init_pass_url')->nullable();

            $table->string('tech_admin_name', 255)->nullable();
            $table->string('tech_admin_username', 255)->nullable();
            $table->string('tech_admin_email', 255);
            $table->string('tech_admin_init_pass_url')->nullable();

            $table->string('instance_code', 10)->unique();

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

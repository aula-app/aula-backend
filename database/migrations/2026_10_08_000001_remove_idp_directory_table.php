<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * User and group webhook payloads actually DO carry the school identifier, so
     * there's no need to track all the entities in the central database.
     */
    public function up(): void
    {
        Schema::dropIfExists('idp_directory');
    }

    public function down(): void
    {
        Schema::create('idp_directory', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 64)->comment('identity provider alias, matches tenants.sso_provider');
            $table->string('entity_type', 32)->comment('user or group');
            $table->string('idp_id', 64);
            $table->string('tenant_id');
            $table->timestamps();

            $table->unique(['provider', 'entity_type', 'idp_id']);
            $table->index('tenant_id');
        });
    }
};

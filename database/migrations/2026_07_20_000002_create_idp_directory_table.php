<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Maps provider user and group ids to the tenant that owns them.
     *
     * User and group webhook payloads carry no school identifier, so nothing in
     * the event says which tenant database to open. TenantResolver reads this
     * index for the answer and writes an entry whenever a school's users or
     * groups are read from the directory.
     */
    public function up(): void
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

    public function down(): void
    {
        Schema::dropIfExists('idp_directory');
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->boolean('sso_enabled')->default(false)->after('jwt_key');
            $table->string('sso_provider', 64)->nullable()->after('sso_enabled')
                ->comment('IdP alias in Keycloak, e.g. mock-iserv, vidis, iserv');
            $table->json('sso_idp_config')->nullable()->after('sso_provider')
                ->comment('Per-tenant IdP config (IServ URL, client_id, etc.)');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['sso_enabled', 'sso_provider', 'sso_idp_config']);
        });
    }
};

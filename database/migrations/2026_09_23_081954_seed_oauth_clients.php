<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;
use Laravel\Passport\ClientRepository;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $clientRepo = new ClientRepository();
        // when confidential:false is set, we don't need client_secret
        $client = $clientRepo->createPasswordGrantClient('password_grants_tenant_users', 'aula_users', false);

        Log::info("Client Name:   {$client->name}");
        Log::info("Client ID:     {$client->id}");
        Log::info('Client Secret: N/A');

        // SsoController uses createToken() to issue sso sessions, which resolves a
        // personal_access client or throws.
        $personalAccessClient = $clientRepo->createPersonalAccessGrantClient(
            'personal_access_tenant_users',
            'aula_users',
        );

        Log::info("Personal Access Client ID: {$personalAccessClient->id}");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};

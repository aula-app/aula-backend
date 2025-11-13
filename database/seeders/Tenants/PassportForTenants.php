<?php

namespace Database\Seeders\Tenants;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Laravel\Passport\ClientRepository;

class PassportForTenants extends Seeder
{
    /**
     * Seed the tenant's database with passport clients.
     */
    public function run(): void
    {
        $tenant = Tenant::query()->whereId(tenant('id'))->first();
        $client = new ClientRepository();

        $client->createPasswordGrantClient('password_'.$tenant['instance_code'], 'aula_users', false);
        $client->createAuthorizationCodeGrantClient($tenant['instance_code'], [$tenant->api_base_url], confidential: false);
        /* print_r($client->createClientCredentialsGrantClient('client1')->plainSecret); */
        /* $client->createPasswordGrantClient('pass_client', 'Default password grant client', 'http://your.redirect.path'); */
        /* $client->createPersonalAccessClient(null, 'Default personal access client', 'http://your.redirect.path'); */

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
        ]);
    }
}

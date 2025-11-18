<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Laravel\Passport\ClientRepository;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $clientRepo = new ClientRepository();
        // when confidential:false is set, we don't need client_secret
        $client = $clientRepo->createPasswordGrantClient('password_central_manager', 'aula_manager_users', false);
        $this->command->info("Client ID:     {$client->id}");
        $this->command->info('Client Secret: N/A');

        $user = User::factory()->create([
            'name' => 'aula devs test',
            'email' => 'dev@aula.de',
            'password' => 'password',
        ]);
        $this->command->info("User Email:    {$user->email}");
        $this->command->info('User Password: password');
    }
}

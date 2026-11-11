<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Password setup links. The legacy set_password controller consumes the
 * secret from au_change_password.
 */
class PasswordSetupLinks
{
    public function url(string $apiBaseUrl, string $instanceCode, string $secret): string
    {
        return "{$apiBaseUrl}/password/{$secret}?code={$instanceCode}";
    }

    /**
     * Replaces any earlier secret for the user. Requires initialised tenancy.
     */
    public function issue(int $userId): string
    {
        $secret = Str::random(64);

        DB::table('au_change_password')->where('user_id', $userId)->delete();
        DB::table('au_change_password')->insert([
            'user_id' => $userId,
            'secret' => $secret,
            'created_at' => now(),
        ]);

        return $secret;
    }
}

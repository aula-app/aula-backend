<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Mail;
use App\Models\LegacyUser;
use Illuminate\Support\Facades\DB;
use App\Mail\UserCreatedWelcomePassword;
use App\Models\Tenant;

class UserService
{
    private const string ALLOWED_PASSWORD_CHARS = '23456789abcdefghijklmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ_!@';

    // "contains at least one of A-Z, a-z, 0-9 and symbol"
    private const string VALID_PASSWORD = '/(?=.*([A-Z]))(?=.*([a-z]))(?=.*([0-9]))(?=.*([~`\!@#\$%\^&\*\(\)_\{\}\[\]]))/';

    private const int FIRST_PASSWORD_LENGTH = 8;
    private const int TEMPORARY_PASSWORD_LENGTH = 8;

    private function generatePassword($length): string
    {
        $retry = 0;
        $max = mb_strlen(self::ALLOWED_PASSWORD_CHARS, '8bit') - 1;
        do {
            $pass = '';
            for ($i = 0; $i < $length; $i++) {
                $pass .= self::ALLOWED_PASSWORD_CHARS[random_int(0, $max)];
            }
            $retry++;
            // TODO: if ($retry > ...) throw...
        } while (!preg_match(self::VALID_PASSWORD, $pass));
        return $pass;
    }

    public function generateTemporaryPassword(): string
    {
        return $this->generatePassword(self::TEMPORARY_PASSWORD_LENGTH);
    }

    public function generateFirstPassword(): string
    {
        // TODO: does this need to do something with passport?
        return $this->generatePassword(self::FIRST_PASSWORD_LENGTH);
    }

    public function getPasswordChangeLink(LegacyUser $legacyUser): string
    {
        $secret = '';
        do {
            $secret = bin2hex(random_bytes(32));
        } while (DB::table('au_change_password')->where('secret', $secret)->exists());

        DB::table('au_change_password')->insert([
            'user_id' => $legacyUser->id,
            'secret' => $secret
        ]);

        /** @var Tenant|null $tenant */
        $tenant = tenant();

        // TODO is this necessary?
        // or will a tenancy error surface much earlier anyway?
        if (!$tenant) {
            abort(500, "no tenant");
        }

        // TODO properly use route once in v2
        return sprintf('%s/password/%s?code=%s',
            config('app.url'),
            $secret,
            // tenant()->instance_code
            $tenant->instance_code,
        );
    }

    public function sendWelcomePassword(string $recipient, LegacyUser $legacyUser): void
    {
        // TODO cron & error handling
        $mailable = new UserCreatedWelcomePassword(
            name: $legacyUser->realname,
            username: $legacyUser->username,
            secretLink: $this->getPasswordChangeLink($legacyUser),
        );
        Mail::to($recipient)->send(clone $mailable);
    }
}

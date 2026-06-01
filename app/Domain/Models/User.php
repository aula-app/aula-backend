<?php

namespace App\Domain\Models;

use App\Enums\UserLevel;

use App\Models\LegacyUser;
use Illuminate\Foundation\Http\FormRequest;

class User
{
    public function __construct(
        public string $displayname,
        public string $username,
        public string $realname,
        public ?string $email,
        public ?UserLevel $userlevel,
        public ?string $about_me
    ) {}

    public int $id;
    public ?string $hash_id;

    public static function fromLegacy(LegacyUser $legacyUser): User
    {
        $user = new self(
            $legacyUser->displayname,
            $legacyUser->username,
            $legacyUser->realname,
            $legacyUser->email,
            $legacyUser->userlevel,
            $legacyUser->about_me,
        );
        $user->id = $legacyUser->id;
        $user->hash_id = $legacyUser->hash_id;
        return $user;
    }

    public static function fromRequest(FormRequest $formRequest): User
    {
        $user = new self(
            $formRequest->validated('displayname'),
            $formRequest->validated('username'),
            $formRequest->validated('realname'),
            $formRequest->validated('email'),
            UserLevel::tryFrom($formRequest->validated('userlevel')),
            $formRequest->validated('about_me')
        );
        return $user;
    }
}

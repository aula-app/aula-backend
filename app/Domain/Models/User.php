<?php

namespace App\Domain\Models;

use App\Enums\UserLevel;
use App\Enums\UserStatus;

use App\Models\LegacyUser;
use DateTimeImmutable;
use Illuminate\Foundation\Http\FormRequest;

class User
{
    public function __construct(
        public string $displayName,
        public string $userName,
        public string $realName,
        public ?string $email,
        public ?UserLevel $userLevel,
        public ?string $aboutMe
    ) {}

    public int $id;
    public ?string $hashId;
    public ?UserStatus $status;
    public ?DateTimeImmutable $createdAt = null;
    public ?DateTimeImmutable $updatedAt = null;

    /**
     * @param LegacyUser $legacyUser
     * @return User
     * @psalm-suppress UndefinedMagicPropertyFetch
     */
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
        $user->hashId = $legacyUser->hash_id;
        $user->status = UserStatus::tryFrom($legacyUser->status);
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
        $user->status = UserStatus::tryFrom($formRequest->validated('status'));
        return $user;
    }
}

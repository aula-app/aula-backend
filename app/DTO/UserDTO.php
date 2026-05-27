<?php

namespace App\DTO;

use App\Enums\UserLevel;

readonly class UserDTO
{
    public function __construct(
        public string $displayname,
        public string $username,
        public string $realname,
        public ?string $email,
        public ?UserLevel $userlevel,
        public ?string $about_me
    ) {
    }
}

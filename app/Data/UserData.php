<?php

namespace App\Data;

use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Validation\Rule;

use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Transformers\DateTimeInterfaceTransformer;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Casts\EnumCast;

use App\Enums\UserLevel;
use App\Enums\UserStatus;
// use DateTimeImmutable;

use Spatie\LaravelData\Optional;

class UserData extends Data
{
    public function __construct(
        readonly public int|Optional $id,

        #[MapName('hash_id')]
        readonly public string|Optional $hashId,

        #[MapInputName('displayname')]
        #[MapOutputName('displayname')]
        #[Max(400)]
        readonly public string $displayName,

        #[MapInputName('username')]
        #[MapOutputName('username')]
        #[Max(400)]
        readonly public string $userName,

        #[MapInputName('realname')]
        #[MapOutputName('realname')]
        #[Max(400)]
        readonly public string $realName,

        readonly public string|Optional $email,

        #[MapInputName('userlevel')]
        #[MapOutputName('userlevel')]
        #[WithCast(EnumCast::class, type: UserLevel::class)]
        readonly public UserLevel|Optional $userLevel,

        #[MapInputName('about_me')]
        #[MapOutputName('about_me')]
        readonly public string|Optional $aboutMe,

        #[WithCast(EnumCast::class, type: UserStatus::class)]
        readonly public UserStatus $status,

        #[MapInputName('created')]
        #[MapOutputName('created')]
        #[WithCast(DateTimeInterfaceCast::class, format: DateTimeInterface::ATOM)]
        #[WithTransformer(DateTimeInterfaceTransformer::class, format: DateTimeInterface::ATOM)]
        readonly public DateTimeImmutable|Optional $createdAt,
    ) {}

    public static function rules($context = null): array
    {
        // We explicitly define these rules so inheritors User{Store,Update}Data
        // can add 'sometimes', resp. 'required'.
        // Otherwise, 'sometimes' is inferred from Optional and we get ['required','sometimes']
        // where 'sometimes' will break 'required'.
        // Hence, we also can't use #[MergeValidationRules] and instead do a custom merge in the
        // child classes.
        return [
            'email' => [
                Rule::email()
            ],
            'userlevel' => [
                Rule::enum(UserLevel::class)
            ],
            'about_me' => [
                'string'
            ]
        ];
    }
}

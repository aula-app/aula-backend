<?php

declare(strict_types=1);

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

abstract class UserData extends Data
{
    // can't specify abstract in constructor promotion
    abstract public int|Optional $id {
        // need hooked property for abstract; equals readonly
        get;
    }

    abstract public string|Optional $hashId { get; }

    // WithCast might not be necessary?
    // #[WithCast(EnumCast::class, type: UserLevel::class)]
    abstract public UserLevel|Optional $userLevel { get; }

    // Validation of abstract must be done in child
    abstract public string|Optional|null $email { get; }

    abstract public string|Optional $aboutMe { get; }

    public function __construct(
        int|Optional $id,
        string|Optional $hashId,

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

        // N.B. nullable!
        string|Optional|null $email,

        UserLevel|Optional $userLevel,

        string|Optional $aboutMe,

        #[WithCast(EnumCast::class, type: UserStatus::class)]
        readonly public UserStatus $status,

        #[MapInputName('created')]
        #[MapOutputName('created')]
        #[WithCast(DateTimeInterfaceCast::class, format: DateTimeInterface::ATOM)]
        #[WithTransformer(DateTimeInterfaceTransformer::class, format: DateTimeInterface::ATOM)]
        readonly public DateTimeImmutable|Optional $createdAt,
    ) {
        // abstract are unpromotable, need to be set up sans sugar
        $this->id = $id;
        $this->hashId = $hashId;
        $this->email = $email;
        $this->userLevel = $userLevel;
        $this->aboutMe = $aboutMe;
    }

    public static function rules(): array
    {
        return [
            // laravel-data does not have #[Missing]
            'created' => ['missing'],
            'hash_id' => ['missing'],
            'id' => ['missing'],
        ];
    }
}

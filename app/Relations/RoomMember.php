<?php

namespace App\Relations;

use App\Enums\RoomUserLevel;
use Illuminate\Database\Eloquent\Relations\Pivot;

class RoomMember extends Pivot
{
    public const RoomUserLevel DEFAULT_ROOM_USER_LEVEL = RoomUserLevel::User;

    protected $casts = [
        'room_user_level' => RoomUserLevel::class,
    ];
}

<?php

namespace App\Relations;

use App\Enums\RoomUserLevel;
use Illuminate\Database\Eloquent\Relations\Pivot;

class RoomMember extends Pivot
{
    protected $casts = [
        'room_user_level' => RoomUserLevel::class,
    ];
}

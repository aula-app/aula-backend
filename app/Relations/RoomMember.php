<?php

namespace App\Relations;

use App\Enums\RoomMemberRole;
use Illuminate\Database\Eloquent\Relations\Pivot;

class RoomMember extends Pivot
{
    protected $casts = [
        'room_member_role' => RoomMemberRole::class,
    ];
}

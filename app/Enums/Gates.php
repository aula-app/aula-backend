<?php

declare(strict_types=1);

namespace App\Enums;

enum Gates
{
    case ListUsers;
    case CreateUser;
    case ShowUser;
    case UpdateUser;
    case DeleteUser;

    case ListRooms;
    case CreateRoom;
    case ShowRoom;
    case UpdateRoom;
    case DeleteRoom;

    case ListRoomMembership;
    case PatchRoomMembership;

    case ListRoomMember;
    case CreateRoomMember;
    case ShowRoomMember;
    case DeleteRoomMember;
}

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

    case ListRoomUser;
    case CreateRoomUser;
    case ShowRoomUser;
    case DeleteRoomUser;

    case ListIdeas;
    case ListIdeasMine;
    case ListIdeasInMyRooms;
}

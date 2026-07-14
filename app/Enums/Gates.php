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

    case ShowUserGdprInfo;
}

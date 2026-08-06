<?php

namespace App\Models\Manager;

use Laravel\Passport\Client as PassportClient;

class CentralClient extends PassportClient
{
    protected $connection = 'mariadb_central'; // config('tenancy.database.central_connection');
}

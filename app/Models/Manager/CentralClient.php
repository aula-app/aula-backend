<?php

namespace App\Models\Manager;

use Laravel\Passport\Client as PassportClient;

/**
 * Defining our own PassportClient class in order to
 * ensure the Mobile App (and other API Clients) are not
 * Tenant-specific. Until further change of requirements.
 */
class CentralClient extends PassportClient
{
    /**
    * The value must be hardcoded. It must match the value of
    *   config('tenancy.database.central_connection'), which in
    *   turn needs to be found in config('database.connections.*').
    *
    * See ./config/database.php and ./config/tenancy.php.
    *
    * @var string
    */
    protected $connection = 'mariadb_central';
}

<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Maps a provider user or group id to the tenant that owns it.
 *
 * IdpUser and group webhooks carry no school identifier, so this index is what
 * makes an incoming event routable to a tenant database. Entries are written
 * whenever a school's people or groups are read from the directory.
 */
class IdpDirectoryEntry extends Model
{
    use CentralConnection;

    public const string TYPE_USER = 'user';

    public const string TYPE_GROUP = 'group';

    protected $table = 'idp_directory';

    protected $fillable = [
        'provider',
        'entity_type',
        'idp_id',
        'tenant_id',
    ];
}

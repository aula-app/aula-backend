<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LegacyRoom extends Model
{
    protected $table = 'au_rooms';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $casts = [
        'id' => 'integer',
        'created' => 'datetime',
        'last_update' => 'datetime',
    ];
}

<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

use App\Relations\RoomUser;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(LegacyUser::class, 'au_rel_rooms_users', 'room_id', 'user_id')->using(RoomUser::class)->withPivot('room_user_level');
    }

    public function ideas(): HasMany
    {
        return $this->hasMany(Idea::class, 'room_id');
    }
}

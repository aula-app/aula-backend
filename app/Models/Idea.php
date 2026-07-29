<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Idea extends Model
{
    protected $table = 'au_ideas';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $casts = [
        'id' => 'integer',
        'created' => 'datetime',
        'last_update' => 'datetime',
    ];

    public function room(): BelongsTo
    {
        return $this->belongsTo(LegacyRoom::class, 'room_id');
    }
}


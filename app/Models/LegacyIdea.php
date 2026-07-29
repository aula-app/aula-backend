<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Topic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class LegacyIdea extends Model
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(LegacyUser::class, 'user_id');
    }

    /* wrong, unused
    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class, 'topic_id');
    }
    */

    public function topics(): BelongsToMany
    {
        return $this->belongsToMany(Topic::Class, 'au_rel_topics_ideas', 'idea_id', 'topic_id');
    }
}


<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\LegacyIdea;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class LegacyTopic extends Model
{
    protected $table = 'au_topics';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $casts = [
        'id' => 'integer',
        'phase_id' => 'integer',
        'created' => 'datetime',
        'last_update' => 'datetime',
    ];

    public function ideas(): BelongsToMany
    {
        return $this->belongsToMany(LegacyIdea::class, 'au_rel_topics_ideas', 'topic_id', 'idea_id');
    }
}

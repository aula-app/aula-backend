<?php

declare(strict_types=1);

namespace App\UseCases\Idea;

use App\Data\Idea\DomainIdeaData;
use App\Models\Idea;
use App\Models\LegacyUser;
use Illuminate\Support\Facades\Gate;
use Spatie\LaravelData\DataCollection;
use App\Enums\Gates;
use Illuminate\Http\Request;

class ListIdeasMineUseCase
{
    /**
     * @psalm-suppress InvalidReturnType
     * @psalm-suppress InvalidReturnStatement
     * @return DataCollection<array-key, DomainIdeaData>
     */
    public function execute(LegacyUser $user, ?string $roomPublicId, ?string $phaseId): DataCollection
    {
        Gate::authorize(Gates::ListIdeasMine);

        // Gate::authorize(Gates::ListIdeasMine, ['room' => $room]);
        $ideas = Idea::whereRelation('user', 'hash_id', $user->hash_id);
        // TODO: no need to check whether user is in the room?
        // TODO: does the user have access to their ideas that might be "stuck" in rooms where the user has been removed from?
        if ($roomPublicId) {
            $ideas->whereRelation('room', 'hash_id', $roomPublicId);
        }
        if ($phaseId) {
            $ideas->whereRelation('topic', 'phase_id', $phaseId);
        }
        return DomainIdeaData::collect($ideas->get(), DataCollection::class);
    }
}


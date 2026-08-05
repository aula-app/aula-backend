<?php

declare(strict_types=1);

namespace App\UseCases\Idea;

use App\Data\Idea\DomainIdeaData;
use App\Data\Idea\Requests\ListIdeasMineData;
use App\Models\LegacyIdea;
use App\Models\LegacyUser;
use Illuminate\Support\Facades\Gate;
use Spatie\LaravelData\DataCollection;
use App\Enums\Gates;

class ListIdeasMineUseCase
{
    /**
     * @psalm-suppress InvalidReturnType
     * @psalm-suppress InvalidReturnStatement
     * @return DataCollection<array-key, DomainIdeaData>
     */
    public function execute(LegacyUser $user, ListIdeasMineData $listIdeasMineData): DataCollection
    {
        Gate::authorize(Gates::ListIdeasMine);

        // Gate::authorize(Gates::ListIdeasMine, ['room' => $room]);
        $userPublicId = 'Xn6T2leuS1P0g7oXbXVsIsaFQdgnGStF'; // $user->hash_id;
        $ideas = LegacyIdea::whereRelation('user', 'hash_id', $userPublicId);
        // TODO: no need to check whether user is in the room?
        // TODO: does the user have access to their ideas that might be "stuck" in rooms where the user has been removed from?
        if ($listIdeasMineData->roomPublicId) {
            $ideas->whereRelation('room', 'hash_id', $listIdeasMineData->roomPublicId);
        }
        if ($listIdeasMineData->phaseId) {
            $ideas->whereRelation('topic', 'phase_id', $listIdeasMineData->phaseId);
        }
        $ideas->with('room:id,hash_id');
        return DomainIdeaData::collect($ideas->get(), DataCollection::class);
    }
}


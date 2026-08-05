<?php

declare(strict_types=1);

namespace App\UseCases\Idea;

use App\Data\Idea\DomainIdeaData;
use App\Models\LegacyIdea;
use App\Models\LegacyUser;
use Illuminate\Support\Facades\Gate;
use Spatie\LaravelData\DataCollection;
use App\Enums\Gates;
use Illuminate\Http\Request;

class ListIdeasInMyRoomsUseCase
{
    /**
     * @psalm-suppress InvalidReturnType
     * @psalm-suppress InvalidReturnStatement
     * @return DataCollection<array-key, DomainIdeaData>
     */
    public function execute(LegacyUser $user, ?string $phaseId): DataCollection
    {
        Gate::authorize(Gates::ListIdeasInMyRooms);

        // TODO: probably not idiomatic? somehow querybuilderize?
        $ideas = LegacyIdea::whereIn('room_id', $user->rooms->pluck('id'));

        if ($phaseId !== null) {
            // $ideas->whereAttachedTo()
            $ideas->whereRelation('topics', 'phase_id', '=', $phaseId);
        }

        return DomainIdeaData::collect($ideas->with('topics')->get(), DataCollection::class);
    }
}


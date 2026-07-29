<?php

declare(strict_types=1);

namespace App\UseCases\Idea;

use App\Data\Idea\DomainIdeaData;
use App\Models\Idea;
use Illuminate\Support\Facades\Gate;
use Spatie\LaravelData\DataCollection;
use App\Enums\Gates;

class ListIdeasUseCase
{
    /**
     * @psalm-suppress InvalidReturnType
     * @psalm-suppress InvalidReturnStatement
     * @return DataCollection<array-key, DomainIdeaData>
     */
    public function execute(): DataCollection
    {
        Gate::authorize(Gates::ListIdeas);

        $all = Idea::with('rooms:hash_id')->get();
        return DomainIdeaData::collect($all, DataCollection::class);
    }
}


<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Data\Idea\DomainIdeaData;
use App\Data\Idea\Requests\StoreIdeaData;
use App\Data\Idea\Requests\UpdateIdeaData;
use App\UseCases\Idea\CreateIdeaUseCase;
use App\UseCases\Idea\DeleteIdeaUseCase;
use App\UseCases\Idea\ListIdeasUseCase;
use App\UseCases\Idea\ShowIdeaUseCase;
use App\UseCases\Idea\UpdateIdeaUseCase;
use Spatie\LaravelData\DataCollection;

class IdeaController extends Controller
{
    public function __construct(
        protected CreateIdeaUseCase $createIdeaUseCase,
        protected ShowIdeaUseCase $showIdeaUseCase,
        protected ListIdeasUseCase $listIdeasUseCase,
        protected UpdateIdeaUseCase $updateIdeaUseCase,
        protected DeleteIdeaUseCase $deleteIdeaUseCase,
    ) {
    }

    public function index(): DataCollection
    {
        return $this->listIdeasUseCase->execute();
    }

    public function show(string $publicId): DomainIdeaData
    {
        return $this->showIdeaUseCase->execute($publicId);
    }

    public function store(StoreIdeaData $userStoreData): DomainIdeaData
    {
        return $this->createIdeaUseCase->execute($userStoreData);
    }

    public function update(string $publicId, UpdateIdeaData $updateIdeaData): DomainIdeaData
    {
        return $this->updateIdeaUseCase->execute($publicId, $updateIdeaData);
    }

    public function destroy(string $publicId): void
    {
        $this->deleteIdeaUseCase->execute($publicId);
    }
}


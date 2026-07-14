<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\UseCases\UserGdprInfo\ShowUserGdprInfoUseCase;

class UserGdprInfoController extends Controller
{
    public function __construct(
        protected ShowUserGdprInfoUseCase $showUserGdprInfoUseCase,
    ) {
    }

    public function show(string $publicId): array
    {
        return $this->showUserGdprInfoUseCase->execute($publicId);
    }
}

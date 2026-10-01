<?php

declare(strict_types=1);

namespace App\UseCases\User;

use App\Data\User\DomainUserData;
use App\Enums\Gates;
use App\Models\LegacyUser;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;

class ExportUserGdprInfoUseCase
{
    public function execute(string $publicId): string
    {
        Gate::authorize(Gates::ExportUserGdprInfo, [$publicId]);

        $legacyUser = LegacyUser::where('hash_id', $publicId)->firstOrFail();
        $user = DomainUserData::from($legacyUser);
        $userId = $legacyUser->id;

        // TODO: refactor query once relation is established
        $ideas = DB::table('au_ideas')->where('user_id', $userId)
            ->get(['title', 'content', 'created', 'last_update']);

        $comments = DB::table('au_comments')->where('user_id', $userId)
            ->get(['content', 'created', 'last_update']);

        $data = [
            "user" => $user->toArray(),
            "userIdeas" => $ideas->toArray(),
            "userComments" => $comments->toArray(),
        ];

        return json_encode($data, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    }
}


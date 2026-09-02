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
    public function execute(string $publicId): array
    {
        Gate::authorize(Gates::ExportUserGdprInfo, [$publicId]);

        $legacyUser = LegacyUser::where('hash_id', $publicId)->firstOrFail();
        $user = DomainUserData::from($legacyUser);
        $userId = $legacyUser->id;

        // TODO: refactor query once relation is established
        $ideas = DB::table('au_ideas')->where('user_id', $userId)
            ->get(['content', 'created', 'last_update']);
        // serialization format from legacy User.php
        $ideasFlat = $ideas->map(fn ($idea) =>
            "{$idea->content}, IDEA CREATED: {$idea->created}, IDEA LAST UPDATE: {$idea->last_update}*§$"
        )
            ->implode('');

        $comments = DB::table('au_comments')->where('user_id', $userId)
            ->get(['content', 'created', 'last_update']);
        // serialization format from legacy User.php
        $commentsFlat = $comments->map(fn ($comment) =>
            "{$comment->content}, COMMENT CREATED: {$comment->created}, COMMENT LAST UPDATE: {$comment->last_update}*§$"
        )
            ->implode('');

        return [
            "user" => $user->toArray(),
            "userIdeas" => $ideasFlat,
            "userComments" => $commentsFlat,
        ];
    }
}


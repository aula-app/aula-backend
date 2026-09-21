<?php

declare(strict_types=1);

namespace App\UseCases\Idp;

use App\Enums\Gates;
use App\Models\IdpMergeCandidate;
use App\Services\Idp\Migration\MergeProposalBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * A page of idp_merge_candidates for the admin review, with the aula avatar
 * of each user row so the reviewer can tell two accounts of one name apart.
 *
 * Admin-only: the rows settle which account each directory identity ends up
 * on. Requires initialised tenancy.
 */
final class ListMergeProposalsUseCase
{
    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function execute(MergeProposalFilter $filter): LengthAwarePaginator
    {
        Gate::authorize(Gates::ListMergeProposals);

        $query = IdpMergeCandidate::query();

        if ($filter->kind !== null) {
            $query->where('kind', $filter->kind);
        }

        if ($filter->search !== null) {
            $query->where(function (Builder $q) use ($filter): void {
                $q->where('local_name', 'like', "%{$filter->search}%")
                    ->orWhere('idp_name', 'like', "%{$filter->search}%");
            });
        }

        match ($filter->bucket) {
            MergeProposalFilter::BUCKET_MERGES => $query->whereNotNull('idp_id')->whereNotNull('local_id'),
            MergeProposalFilter::BUCKET_IDP_ONLY => $query->whereNotNull('idp_id')->whereNull('local_id'),
            MergeProposalFilter::BUCKET_AULA_ONLY => $query->whereNull('idp_id')->whereNotNull('local_id'),
            default => null,
        };

        /** @var LengthAwarePaginator<int, IdpMergeCandidate> $page */
        $page = $query->orderBy('kind')->orderBy('outcome')->orderBy('id')
            ->paginate(perPage: $filter->perPage, page: $filter->page);

        $avatars = $this->avatarsFor($page->items());

        /** @var LengthAwarePaginator<int, array<string, mixed>> $rows */
        $rows = $page->through(fn (IdpMergeCandidate $row): array => $row->toArray() + [
            'local_avatar' => $row->kind === MergeProposalBuilder::KIND_USER && $row->local_id !== null
                ? ($avatars[$row->local_id] ?? null)
                : null,
        ]);

        return $rows;
    }

    /**
     * Avatar filename by user id. The frontend resolves it against /api/files.
     *
     * system_type 0 is the avatar, and legacy Media::addMedia() deletes the
     * previous one before inserting, so there is at most one row per user.
     *
     * @param  list<IdpMergeCandidate>  $rows
     * @return array<int, string>
     */
    private function avatarsFor(array $rows): array
    {
        $userIds = [];

        foreach ($rows as $row) {
            if ($row->kind === MergeProposalBuilder::KIND_USER && $row->local_id !== null) {
                $userIds[] = $row->local_id;
            }
        }

        if ($userIds === []) {
            return [];
        }

        return DB::table('au_media')
            ->where('system_type', 0)
            ->whereIn('updater_id', $userIds)
            ->pluck('filename', 'updater_id')
            ->all();
    }
}

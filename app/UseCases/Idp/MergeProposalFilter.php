<?php

declare(strict_types=1);

namespace App\UseCases\Idp;

/**
 * Which idp_merge_candidates rows an admin is looking at.
 *
 * A thousand-row proposal is unusable unfiltered, so a listing is always
 * paged and can be narrowed by kind, bucket and a name search. Blank and
 * unknown values mean "no filter", and the page size is capped here so no
 * caller can ask for the whole table at once.
 */
final readonly class MergeProposalFilter
{
    /** Rows with an identity on both sides. */
    public const string BUCKET_MERGES = 'merges';

    /** Rows that exist at the provider alone. */
    public const string BUCKET_IDP_ONLY = 'idp_only';

    /** Rows that exist in aula alone. */
    public const string BUCKET_AULA_ONLY = 'aula_only';

    public const array BUCKETS = [self::BUCKET_MERGES, self::BUCKET_IDP_ONLY, self::BUCKET_AULA_ONLY];

    public const int DEFAULT_PER_PAGE = 50;

    public const int MAX_PER_PAGE = 200;

    public ?string $kind;

    public ?string $search;

    public ?string $bucket;

    public int $page;

    public int $perPage;

    public function __construct(
        ?string $kind = null,
        ?string $search = null,
        ?string $bucket = null,
        int $page = 1,
        int $perPage = self::DEFAULT_PER_PAGE,
    ) {
        $this->kind = self::blankToNull($kind);
        $this->search = self::blankToNull($search);
        $this->bucket = in_array($bucket, self::BUCKETS, true) ? $bucket : null;
        $this->page = max(1, $page);
        $this->perPage = max(1, min(self::MAX_PER_PAGE, $perPage));
    }

    private static function blankToNull(?string $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}

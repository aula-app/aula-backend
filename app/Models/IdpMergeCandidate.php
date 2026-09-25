<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One row of the merge proposal an admin reviews before a school's directory
 * is imported over its existing accounts.
 *
 * Which side is null says which bucket the row is in: both ids set is a
 * proposed merge, idp_id alone exists at the provider only, local_id alone
 * exists in aula only. MergeProposalBuilder writes the rows and
 * MergeProposalApplier acts on the confirmed ones.
 *
 * Tenant table.
 */
class IdpMergeCandidate extends Model
{
    protected $table = 'idp_merge_candidates';

    protected $fillable = [
        'kind',
        'idp_id',
        'idp_name',
        'idp_name_kind',
        'idp_groups',
        'local_id',
        'local_name',
        'outcome',
        'decision',
    ];

    protected $casts = [
        'local_id' => 'integer',
        'idp_groups' => 'array',
    ];
}

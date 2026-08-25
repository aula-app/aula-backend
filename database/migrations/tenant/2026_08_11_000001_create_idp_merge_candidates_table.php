<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The proposal for reconciling a school's existing aula rows with its
     * directory, held between MergeProposalBuilder writing it and
     * MergeProposalApplier applying it.
     *
     * Stored rather than recomputed on submit: a review can take an hour and
     * the directory can change underneath it, so storing the rows is what stops
     * a pairing the admin never saw being applied.
     *
     * One table covers all three buckets, by which side is null:
     *
     *   idp_id + local_id   a proposed merge
     *   idp_id only         exists at the provider, becomes a new row
     *   local_id only       exists in aula alone, keeps password login
     */
    public function up(): void
    {
        Schema::create('idp_merge_candidates', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 8)->comment('user or room');

            $table->string('idp_id', 64)->nullable();
            $table->string('idp_name', 255)->nullable();
            $table->string('idp_name_kind', 16)->nullable()
                ->comment('real or pseudonym; a pseudonym can never be matched by name');

            $table->unsignedInteger('local_id')->nullable()->comment('au_users_basedata.id or au_rooms.id');
            $table->string('local_name', 255)->nullable();

            $table->string('outcome', 16)->comment('confident, ambiguous or none');
            $table->string('decision', 16)->nullable()->comment('merge or create; null until the admin decides');

            $table->timestamps();

            $table->index(['kind', 'outcome']);
            $table->index('idp_id');
            $table->index('local_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idp_merge_candidates');
    }
};

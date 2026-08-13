<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * hash_id is the API's public user identifier and, since user_id left the JWT
 * payload, the claim authentication resolves on. That makes uniqueness a
 * security invariant rather than a nicety, and the lookup is on the hot path of
 * every authenticated request.
 *
 * varchar(1024) cannot be indexed under utf8mb4 (1024 * 4 > the 3072-byte key
 * limit), so the column is narrowed first. Values are md5 hex digests, 32 chars.
 */
return new class extends Migration
{
    private const TABLE = 'au_users_basedata';

    private const INDEX = 'au_users_basedata_hash_id_unique';

    public function up(): void
    {
        $this->assertNoDuplicateHashIds();

        Schema::table(self::TABLE, function (Blueprint $table) {
            $table->string('hash_id', 255)
                ->nullable()
                ->comment('hashed id userspecific')
                ->change();
        });

        $this->assertNoDuplicateHashIds();

        Schema::table(self::TABLE, function (Blueprint $table) {
            $table->unique('hash_id', self::INDEX);
        });
    }

    public function down(): void
    {
        Schema::table(self::TABLE, function (Blueprint $table) {
            $table->dropUnique(self::INDEX);
        });

        Schema::table(self::TABLE, function (Blueprint $table) {
            $table->string('hash_id', 1024)
                ->nullable()
                ->comment('hashed id userspecific')
                ->change();
        });
    }

    /**
     * Fail the deploy loudly rather than let the index creation surface as an
     * opaque driver error. NULLs are exempt: they are permitted by a unique
     * index and legacy rows are allowed to have them.
     */
    private function assertNoDuplicateHashIds(): void
    {
        $duplicates = DB::table(self::TABLE)
            ->select('hash_id')
            ->whereNotNull('hash_id')
            ->groupBy('hash_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('hash_id')
            ->all();

        if ($duplicates === []) {
            return;
        }

        throw new RuntimeException(sprintf(
            'Cannot add a unique index: %d duplicate hash_id value(s) in %s (%s). '
            .'These are an authentication hazard and must be resolved before migrating.',
            count($duplicates),
            self::TABLE,
            implode(', ', array_slice($duplicates, 0, 10)),
        ));
    }
};

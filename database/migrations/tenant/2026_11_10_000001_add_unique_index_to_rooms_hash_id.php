<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * hash_id is the API's public room identifier
 * while not as security-critical we still enforce uniqueness
 * following 2026_07_27_000001_add_unique_index_to_users_hash_id.php
 */
return new class () extends Migration {
    private const string TABLE = 'au_rooms';

    private const string INDEX = 'au_rooms_basedata_hash_id_unique';

    public function up(): void
    {
        $this->assertNoDuplicateHashIds();

        Schema::table(self::TABLE, function (Blueprint $table) {
            $table->string('hash_id', 255)
                ->nullable()
                ->comment('hashed id roomspecific')
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
                ->comment('hashed id roomspecific')
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
            'Cannot add a unique index: %d duplicate hash_id value(s) in %s (%s).',
            count($duplicates),
            self::TABLE,
            implode(', ', array_slice($duplicates, 0, 10)),
        ));
    }
};

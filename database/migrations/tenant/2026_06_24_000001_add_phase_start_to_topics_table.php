<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('au_topics', function (Blueprint $table) {
            if (! Schema::hasColumn('au_topics', 'phase_start')) {
                $table->dateTime('phase_start')->nullable()->after('phase_id')
                    ->comment('Timestamp when the current phase (phase_id) started; resets the phase countdown');
            }
        });
    }

    public function down(): void
    {
        Schema::table('au_topics', function (Blueprint $table) {
            if (Schema::hasColumn('au_topics', 'phase_start')) {
                $table->dropColumn('phase_start');
            }
        });
    }
};

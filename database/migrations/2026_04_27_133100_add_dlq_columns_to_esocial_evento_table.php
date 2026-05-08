<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('ESOCIAL_EVENTO')) {
            return;
        }

        Schema::table('ESOCIAL_EVENTO', function (Blueprint $table) {
            if (!Schema::hasColumn('ESOCIAL_EVENTO', 'DEAD_LETTER_AT')) {
                $table->timestamp('DEAD_LETTER_AT')->nullable();
            }
            if (!Schema::hasColumn('ESOCIAL_EVENTO', 'DEAD_LETTER_REASON')) {
                $table->string('DEAD_LETTER_REASON', 255)->nullable();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('ESOCIAL_EVENTO')) {
            return;
        }

        Schema::table('ESOCIAL_EVENTO', function (Blueprint $table) {
            if (Schema::hasColumn('ESOCIAL_EVENTO', 'DEAD_LETTER_AT')) {
                $table->dropColumn('DEAD_LETTER_AT');
            }
            if (Schema::hasColumn('ESOCIAL_EVENTO', 'DEAD_LETTER_REASON')) {
                $table->dropColumn('DEAD_LETTER_REASON');
            }
        });
    }
};


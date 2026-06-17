<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('LOTACAO')) {
            return;
        }
        if (Schema::hasColumn('LOTACAO', 'LOTACAO_OBSERVACAO')) {
            return;
        }

        Schema::table('LOTACAO', function (Blueprint $table) {
            $table->text('LOTACAO_OBSERVACAO')->nullable();
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('LOTACAO')) {
            return;
        }
        if (!Schema::hasColumn('LOTACAO', 'LOTACAO_OBSERVACAO')) {
            return;
        }

        Schema::table('LOTACAO', function (Blueprint $table) {
            $table->dropColumn('LOTACAO_OBSERVACAO');
        });
    }
};


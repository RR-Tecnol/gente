<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('DIFF_RECONCILIACAO')) {
            return;
        }
        if (!Schema::hasColumn('DIFF_RECONCILIACAO', 'RUBRICA_CODIGO')) {
            Schema::table('DIFF_RECONCILIACAO', function (Blueprint $table) {
                $table->string('RUBRICA_CODIGO', 64)->nullable();
            });
        }
        if (!Schema::hasColumn('DIFF_RECONCILIACAO', 'RUBRICA_TIPO')) {
            Schema::table('DIFF_RECONCILIACAO', function (Blueprint $table) {
                $table->string('RUBRICA_TIPO', 24)->nullable();
            });
        }
        if (!Schema::hasColumn('DIFF_RECONCILIACAO', 'AGREGACAO')) {
            Schema::table('DIFF_RECONCILIACAO', function (Blueprint $table) {
                $table->string('AGREGACAO', 20)->default('liquido');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('DIFF_RECONCILIACAO')) {
            return;
        }
        if (Schema::hasColumn('DIFF_RECONCILIACAO', 'AGREGACAO')) {
            Schema::table('DIFF_RECONCILIACAO', function (Blueprint $table) {
                $table->dropColumn('AGREGACAO');
            });
        }
        if (Schema::hasColumn('DIFF_RECONCILIACAO', 'RUBRICA_TIPO')) {
            Schema::table('DIFF_RECONCILIACAO', function (Blueprint $table) {
                $table->dropColumn('RUBRICA_TIPO');
            });
        }
        if (Schema::hasColumn('DIFF_RECONCILIACAO', 'RUBRICA_CODIGO')) {
            Schema::table('DIFF_RECONCILIACAO', function (Blueprint $table) {
                $table->dropColumn('RUBRICA_CODIGO');
            });
        }
    }
};

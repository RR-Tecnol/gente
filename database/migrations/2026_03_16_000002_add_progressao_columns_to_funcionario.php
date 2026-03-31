<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // FUNCIONARIO precisa de CARGO_ID para JOIN com CARGO na progressão funcional
        if (!Schema::hasColumn('FUNCIONARIO', 'CARGO_ID')) {
            Schema::table('FUNCIONARIO', function (Blueprint $table) {
                $table->integer('CARGO_ID')->nullable()->default(null);
            });
        }

        // FUNCIONARIO_DATA_INICIO e FUNCIONARIO_ESTAGIO_PROBATORIO usados no $avaliarEleg
        if (!Schema::hasColumn('FUNCIONARIO', 'FUNCIONARIO_DATA_INICIO')) {
            Schema::table('FUNCIONARIO', function (Blueprint $table) {
                $table->date('FUNCIONARIO_DATA_INICIO')->nullable();
            });
        }

        if (!Schema::hasColumn('FUNCIONARIO', 'FUNCIONARIO_ESTAGIO_PROBATORIO')) {
            Schema::table('FUNCIONARIO', function (Blueprint $table) {
                $table->boolean('FUNCIONARIO_ESTAGIO_PROBATORIO')->nullable()->default(0);
            });
        }

        if (!Schema::hasColumn('FUNCIONARIO', 'FUNCIONARIO_DATA_ULTIMA_PROGRESSAO')) {
            Schema::table('FUNCIONARIO', function (Blueprint $table) {
                $table->date('FUNCIONARIO_DATA_ULTIMA_PROGRESSAO')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::table('FUNCIONARIO', function (Blueprint $table) {
            foreach (['CARGO_ID', 'FUNCIONARIO_DATA_INICIO', 'FUNCIONARIO_ESTAGIO_PROBATORIO', 'FUNCIONARIO_DATA_ULTIMA_PROGRESSAO'] as $col) {
                if (Schema::hasColumn('FUNCIONARIO', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};

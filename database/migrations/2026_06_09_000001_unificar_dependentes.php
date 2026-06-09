<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1a — Completar PESSOA_DEPENDENTE com campos que existiam só em DEPENDENTE
        Schema::table('PESSOA_DEPENDENTE', function (Blueprint $table) {
            if (!Schema::hasColumn('PESSOA_DEPENDENTE', 'PESSOA_DEPENDENTE_SEXO'))
                $table->tinyInteger('PESSOA_DEPENDENTE_SEXO')->nullable();
            if (!Schema::hasColumn('PESSOA_DEPENDENTE', 'PESSOA_DEPENDENTE_DT_INICIO'))
                $table->date('PESSOA_DEPENDENTE_DT_INICIO')->nullable();
            if (!Schema::hasColumn('PESSOA_DEPENDENTE', 'PESSOA_DEPENDENTE_DT_FIM'))
                $table->date('PESSOA_DEPENDENTE_DT_FIM')->nullable();
            if (!Schema::hasColumn('PESSOA_DEPENDENTE', 'PESSOA_DEPENDENTE_MOTIVO_FIM'))
                $table->string('PESSOA_DEPENDENTE_MOTIVO_FIM', 50)->nullable();
        });

        // 1b — Remover campo desnormalizado de PESSOA (motor passará a usar subquery)
        if (Schema::hasColumn('PESSOA', 'PESSOA_DEPENDENTES_IRRF')) {
            Schema::table('PESSOA', function (Blueprint $table) {
                $table->dropColumn('PESSOA_DEPENDENTES_IRRF');
            });
        }

        // 1c — Dropar tabela legada (sem dados em produção)
        Schema::dropIfExists('DEPENDENTE');
    }

    public function down(): void
    {
        Schema::dropIfExists('DEPENDENTE'); // sem rollback de dados
    }
};

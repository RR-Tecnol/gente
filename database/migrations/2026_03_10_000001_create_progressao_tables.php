<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // ── CARREIRA ────────────────────────────────────────────────────
        if (!Schema::hasTable('CARREIRA')) {
            Schema::create('CARREIRA', function (Blueprint $table) {
                $table->increments('CARREIRA_ID');
                $table->string('CARREIRA_NOME', 100);
                $table->string('CARREIRA_REGIME', 20)->default('efetivo'); // efetivo | comissionado
                $table->string('CARREIRA_DESCRICAO', 255)->nullable();
                $table->boolean('CARREIRA_ATIVO')->default(true);
                $table->timestamps();
            });
        }

        // ── TABELA SALARIAL ─────────────────────────────────────────────
        if (!Schema::hasTable('TABELA_SALARIAL')) {
            Schema::create('TABELA_SALARIAL', function (Blueprint $table) {
                $table->increments('TABELA_ID');
                $table->unsignedInteger('CARREIRA_ID');
                $table->string('TABELA_CLASSE', 5);          // A, B, C ...
                $table->string('TABELA_REFERENCIA', 5);      // I, II, III ou 1,2,3 ...
                $table->integer('TABELA_REFERENCIA_ORDEM')->default(0); // para ordenação numérica
                $table->decimal('TABELA_VENCIMENTO_BASE', 12, 2);
                $table->string('TABELA_TITULACAO', 20)->nullable(); // medio|graduacao|especializacao|mestrado
                $table->timestamps();
            });
        }

        // ── PROGRESSAO CONFIG ───────────────────────────────────────────
        if (!Schema::hasTable('PROGRESSAO_CONFIG')) {
            Schema::create('PROGRESSAO_CONFIG', function (Blueprint $table) {
                $table->increments('CONFIG_ID');
                $table->unsignedInteger('CARREIRA_ID')->nullable(); // null = padrão global
                $table->integer('CONFIG_INTERSTICIO_MESES')->default(24);
                $table->decimal('CONFIG_NOTA_MINIMA', 5, 2)->default(7.00);
                $table->decimal('CONFIG_ANUENIO_PCT', 5, 2)->default(1.00); // % por ano
                $table->string('CONFIG_REFERENCIA_MAXIMA', 5)->nullable();  // última referência antes de promoção
                $table->string('CONFIG_CLASSE_FINAL', 5)->nullable();       // última classe da carreira
                $table->integer('CONFIG_TEMPO_CLASSE_PROMOCAO_MESES')->default(60); // tempo mínimo na classe para promoção
                $table->integer('CONFIG_ESTAGIO_PROBATORIO_MESES')->default(36);    // duração do estágio
                $table->timestamps();
            });
            // Inserir configuração padrão global
            DB::table('PROGRESSAO_CONFIG')->insert([
                'CARREIRA_ID' => null,
                'CONFIG_INTERSTICIO_MESES' => 24,
                'CONFIG_NOTA_MINIMA' => 7.00,
                'CONFIG_ANUENIO_PCT' => 1.00,
                'CONFIG_REFERENCIA_MAXIMA' => null,
                'CONFIG_CLASSE_FINAL' => null,
                'CONFIG_TEMPO_CLASSE_PROMOCAO_MESES' => 60,
                'CONFIG_ESTAGIO_PROBATORIO_MESES' => 36,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // ── HISTÓRICO FUNCIONAL ─────────────────────────────────────────
        if (!Schema::hasTable('HISTORICO_FUNCIONAL')) {
            Schema::create('HISTORICO_FUNCIONAL', function (Blueprint $table) {
                $table->increments('HISTORICO_ID');
                $table->unsignedInteger('FUNCIONARIO_ID');
                $table->string('HISTORICO_TIPO', 30); // progressao|promocao|reenquadramento|titulacao|ingresso
                $table->string('HISTORICO_CLASSE_ANTES', 5)->nullable();
                $table->string('HISTORICO_REFERENCIA_ANTES', 5)->nullable();
                $table->string('HISTORICO_CLASSE_DEPOIS', 5)->nullable();
                $table->string('HISTORICO_REFERENCIA_DEPOIS', 5)->nullable();
                $table->decimal('HISTORICO_SALARIO_ANTES', 12, 2)->nullable();
                $table->decimal('HISTORICO_SALARIO_DEPOIS', 12, 2)->nullable();
                $table->string('HISTORICO_ATO_ADMINISTRATIVO', 100)->nullable(); // ex: Portaria 123/2026
                $table->date('HISTORICO_DATA_EFEITO')->nullable();
                $table->text('HISTORICO_OBSERVACAO')->nullable();
                $table->unsignedInteger('USUARIO_REGISTROU')->nullable(); // quem aplicou no sistema
                $table->timestamps();
            });
        }

        // ── RECEITA MUNICÍPIO (para cálculo LRF) ───────────────────────
        if (!Schema::hasTable('RECEITA_MUNICIPIO')) {
            Schema::create('RECEITA_MUNICIPIO', function (Blueprint $table) {
                $table->increments('RECEITA_ID');
                $table->integer('RECEITA_ANO');
                $table->decimal('RECEITA_CORRENTE_LIQUIDA', 15, 2); // RCL anual
                $table->decimal('RECEITA_FOLHA_ATUAL', 15, 2)->nullable(); // folha mensal atual
                $table->timestamps();
            });
            // Dado fictício para desenvolvimento (RH deve atualizar)
            DB::table('RECEITA_MUNICIPIO')->insert([
                'RECEITA_ANO' => now()->year,
                'RECEITA_CORRENTE_LIQUIDA' => 50000000.00, // R$ 50 milhões (placeholder)
                'RECEITA_FOLHA_ATUAL' => 2000000.00,  // R$ 2 milhões/mês (placeholder)
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // ── COLUNAS NOVAS EM FUNCIONARIO ────────────────────────────────
        if (Schema::hasTable('FUNCIONARIO')) {
            $cols = [
                ['FUNCIONARIO_CLASSE', fn($t) => $t->string('FUNCIONARIO_CLASSE', 5)->nullable()->after('FUNCIONARIO_ID')],
                ['FUNCIONARIO_REFERENCIA', fn($t) => $t->string('FUNCIONARIO_REFERENCIA', 5)->nullable()->after('FUNCIONARIO_CLASSE')],
                ['FUNCIONARIO_ESTAVEL', fn($t) => $t->boolean('FUNCIONARIO_ESTAVEL')->default(false)->after('FUNCIONARIO_REFERENCIA')],
                ['FUNCIONARIO_ESTAGIO_PROBATORIO', fn($t) => $t->boolean('FUNCIONARIO_ESTAGIO_PROBATORIO')->default(false)->after('FUNCIONARIO_ESTAVEL')],
                ['FUNCIONARIO_DATA_ULTIMA_PROGRESSAO', fn($t) => $t->date('FUNCIONARIO_DATA_ULTIMA_PROGRESSAO')->nullable()->after('FUNCIONARIO_ESTAGIO_PROBATORIO')],
                ['CARREIRA_ID', fn($t) => $t->unsignedInteger('CARREIRA_ID')->nullable()->after('FUNCIONARIO_DATA_ULTIMA_PROGRESSAO')],
            ];
            Schema::table('FUNCIONARIO', function (Blueprint $table) use ($cols) {
                foreach ($cols as [$col, $fn]) {
                    if (!Schema::hasColumn('FUNCIONARIO', $col)) {
                        $fn($table);
                    }
                }
            });
        }
    }

    public function down(): void
    {
        // Remover colunas de FUNCIONARIO
        if (Schema::hasTable('FUNCIONARIO')) {
            Schema::table('FUNCIONARIO', function (Blueprint $table) {
                foreach ([
                    'FUNCIONARIO_CLASSE',
                    'FUNCIONARIO_REFERENCIA',
                    'FUNCIONARIO_ESTAVEL',
                    'FUNCIONARIO_ESTAGIO_PROBATORIO',
                    'FUNCIONARIO_DATA_ULTIMA_PROGRESSAO',
                    'CARREIRA_ID'
                ] as $col) {
                    if (Schema::hasColumn('FUNCIONARIO', $col))
                        $table->dropColumn($col);
                }
            });
        }

        Schema::dropIfExists('RECEITA_MUNICIPIO');
        Schema::dropIfExists('HISTORICO_FUNCIONAL');
        Schema::dropIfExists('PROGRESSAO_CONFIG');
        Schema::dropIfExists('TABELA_SALARIAL');
        Schema::dropIfExists('CARREIRA');
    }
};

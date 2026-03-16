<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // ── ESCALA ─────────────────────────────────────────────
        // Cabeçalho da escala (ex: Março/2026 – UTI Adulto)
        if (!Schema::hasTable('ESCALA')) {
            Schema::create('ESCALA', function (Blueprint $table) {
                $table->increments('ESCALA_ID');
                $table->string('ESCALA_COMPETENCIA', 7)->comment('Formato MM/YYYY, ex: 03/2026');
                $table->unsignedInteger('SETOR_ID')->nullable()->comment('Setor da escala (null = todos os setores)');
                $table->string('ESCALA_STATUS', 20)->default('Aberta')->comment('Aberta, Fechada, Publicada');
                $table->text('ESCALA_OBSERVACAO')->nullable();
                $table->timestamps();

                $table->index(['ESCALA_COMPETENCIA', 'SETOR_ID']);
            });
        }

        // ── DETALHE_ESCALA ─────────────────────────────────────
        // Vínculo entre escala e funcionário (um por funcionário por escala)
        if (!Schema::hasTable('DETALHE_ESCALA')) {
            Schema::create('DETALHE_ESCALA', function (Blueprint $table) {
                $table->increments('DETALHE_ESCALA_ID');
                $table->unsignedInteger('ESCALA_ID');
                $table->unsignedInteger('FUNCIONARIO_ID');
                $table->string('DETALHE_ESCALA_CARGO', 100)->nullable()->comment('Cargo exibido na escala');
                $table->timestamps();

                $table->index('ESCALA_ID');
                $table->index('FUNCIONARIO_ID');
                $table->unique(['ESCALA_ID', 'FUNCIONARIO_ID'], 'uq_escala_funcionario');
            });
        }

        // ── DETALHE_ESCALA_ITEM ────────────────────────────────
        // Um item por dia trabalhado (dia + turno)
        if (!Schema::hasTable('DETALHE_ESCALA_ITEM')) {
            Schema::create('DETALHE_ESCALA_ITEM', function (Blueprint $table) {
                $table->increments('DETALHE_ESCALA_ITEM_ID');
                $table->unsignedInteger('DETALHE_ESCALA_ID');
                $table->date('DETALHE_ESCALA_ITEM_DATA');
                $table->string('TURNO_SIGLA', 5)->nullable()->comment('M, T, N, P, F, AF');
                $table->unsignedInteger('TURNO_ID')->nullable()->comment('ID do turno (se houver tabela separada)');
                $table->string('DETALHE_ESCALA_ITEM_OBS', 255)->nullable();
                $table->timestamps();

                $table->index('DETALHE_ESCALA_ID');
                $table->index('DETALHE_ESCALA_ITEM_DATA');
                $table->unique(
                    ['DETALHE_ESCALA_ID', 'DETALHE_ESCALA_ITEM_DATA'],
                    'uq_detalhe_escala_data'
                );
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('DETALHE_ESCALA_ITEM');
        Schema::dropIfExists('DETALHE_ESCALA');
        Schema::dropIfExists('ESCALA');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cria as tabelas de Verbas Indenizatórias.
 *
 * VERBA_TIPO  — catálogo configurável de tipos de verba (mensais e rescisórias)
 * VERBA_LANCAMENTO — lançamentos mensais por servidor
 */
return new class extends Migration {
    public function up(): void
    {
        // ── VERBA_TIPO ─────────────────────────────────────────────────────────
        if (!Schema::hasTable('VERBA_TIPO')) {
            Schema::create('VERBA_TIPO', function (Blueprint $table) {
                $table->increments('VERBA_TIPO_ID');
                $table->string('VERBA_NOME', 100);
                // MENSAL = recorrente na folha | RESCISORIA = gerada no desligamento
                $table->string('VERBA_GRUPO', 20)->default('MENSAL');
                $table->boolean('INCIDE_IR')->default(false);
                $table->boolean('INCIDE_INSS')->default(false);
                $table->boolean('INCIDE_RPPS')->default(false);
                $table->boolean('REQUER_COMPROVANTE')->default(false);
                // JSON: array de VINCULO_IDs com direito (null = todos os vínculos)
                $table->text('VINCULO_IDS')->nullable();
                $table->boolean('ATIVO')->default(true);
                $table->timestamps();
            });

            // Seed: tipos de verbas mensais padrão
            \Illuminate\Support\Facades\DB::table('VERBA_TIPO')->insert([
                ['VERBA_NOME' => 'Auxílio-Alimentação', 'VERBA_GRUPO' => 'MENSAL', 'INCIDE_IR' => false, 'INCIDE_INSS' => false, 'INCIDE_RPPS' => false, 'REQUER_COMPROVANTE' => false, 'ATIVO' => true, 'created_at' => now(), 'updated_at' => now()],
                ['VERBA_NOME' => 'Auxílio-Transporte', 'VERBA_GRUPO' => 'MENSAL', 'INCIDE_IR' => false, 'INCIDE_INSS' => false, 'INCIDE_RPPS' => false, 'REQUER_COMPROVANTE' => false, 'ATIVO' => true, 'created_at' => now(), 'updated_at' => now()],
                ['VERBA_NOME' => 'Auxílio-Moradia', 'VERBA_GRUPO' => 'MENSAL', 'INCIDE_IR' => false, 'INCIDE_INSS' => false, 'INCIDE_RPPS' => false, 'REQUER_COMPROVANTE' => true, 'ATIVO' => true, 'created_at' => now(), 'updated_at' => now()],
                ['VERBA_NOME' => 'Ajuda de Custo (Remoção)', 'VERBA_GRUPO' => 'MENSAL', 'INCIDE_IR' => false, 'INCIDE_INSS' => false, 'INCIDE_RPPS' => false, 'REQUER_COMPROVANTE' => true, 'ATIVO' => true, 'created_at' => now(), 'updated_at' => now()],
                ['VERBA_NOME' => 'Gratificação SUS / PACS', 'VERBA_GRUPO' => 'MENSAL', 'INCIDE_IR' => false, 'INCIDE_INSS' => false, 'INCIDE_RPPS' => false, 'REQUER_COMPROVANTE' => false, 'ATIVO' => true, 'created_at' => now(), 'updated_at' => now()],
                ['VERBA_NOME' => 'Férias Proporcionais + 1/3', 'VERBA_GRUPO' => 'RESCISORIA', 'INCIDE_IR' => false, 'INCIDE_INSS' => false, 'INCIDE_RPPS' => false, 'REQUER_COMPROVANTE' => false, 'ATIVO' => true, 'created_at' => now(), 'updated_at' => now()],
                ['VERBA_NOME' => 'Férias Vencidas + 1/3', 'VERBA_GRUPO' => 'RESCISORIA', 'INCIDE_IR' => true, 'INCIDE_INSS' => false, 'INCIDE_RPPS' => false, 'REQUER_COMPROVANTE' => false, 'ATIVO' => true, 'created_at' => now(), 'updated_at' => now()],
                ['VERBA_NOME' => '13º Salário Proporcional', 'VERBA_GRUPO' => 'RESCISORIA', 'INCIDE_IR' => true, 'INCIDE_INSS' => true, 'INCIDE_RPPS' => true, 'REQUER_COMPROVANTE' => false, 'ATIVO' => true, 'created_at' => now(), 'updated_at' => now()],
                ['VERBA_NOME' => 'Saldo de Salário', 'VERBA_GRUPO' => 'RESCISORIA', 'INCIDE_IR' => true, 'INCIDE_INSS' => true, 'INCIDE_RPPS' => true, 'REQUER_COMPROVANTE' => false, 'ATIVO' => true, 'created_at' => now(), 'updated_at' => now()],
                ['VERBA_NOME' => 'Licença-Prêmio em Pecúnia', 'VERBA_GRUPO' => 'RESCISORIA', 'INCIDE_IR' => true, 'INCIDE_INSS' => false, 'INCIDE_RPPS' => false, 'REQUER_COMPROVANTE' => false, 'ATIVO' => true, 'created_at' => now(), 'updated_at' => now()],
                ['VERBA_NOME' => 'Indenização por Redução de Quadro', 'VERBA_GRUPO' => 'RESCISORIA', 'INCIDE_IR' => false, 'INCIDE_INSS' => false, 'INCIDE_RPPS' => false, 'REQUER_COMPROVANTE' => false, 'ATIVO' => true, 'created_at' => now(), 'updated_at' => now()],
            ]);
        }

        // ── VERBA_LANCAMENTO ───────────────────────────────────────────────────
        if (!Schema::hasTable('VERBA_LANCAMENTO')) {
            Schema::create('VERBA_LANCAMENTO', function (Blueprint $table) {
                $table->increments('VERBA_LANCAMENTO_ID');
                $table->unsignedInteger('FUNCIONARIO_ID');
                $table->unsignedInteger('VERBA_TIPO_ID');
                $table->string('COMPETENCIA', 7);   // AAAA-MM
                $table->decimal('VALOR', 15, 2);
                $table->text('JUSTIFICATIVA')->nullable();
                $table->string('COMPROVANTE_PATH', 300)->nullable();
                $table->unsignedInteger('UNIDADE_ID')->nullable();  // secretaria — para relatório LRF
                // PENDENTE | APROVADO | INCLUIDO_FOLHA
                $table->string('STATUS', 20)->default('PENDENTE');
                $table->unsignedInteger('LANCADO_POR')->nullable();  // USUARIO_ID
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('VERBA_LANCAMENTO');
        Schema::dropIfExists('VERBA_TIPO');
    }
};

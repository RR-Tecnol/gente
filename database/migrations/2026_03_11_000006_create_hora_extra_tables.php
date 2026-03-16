<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cria as tabelas de Hora Extra e Plantão Extra.
 *
 * HORA_EXTRA    — horas extraordinárias por servidor (além da jornada contratual)
 * PLANTAO_EXTRA — plantões extras além da escala regular (com adicional noturno)
 *
 * Notas:
 *  - Guarda Municipal: verifica Estatuto próprio para regras de HE e adicional noturno
 *  - Adicional noturno: 20% para horas trabalhadas entre 22h e 05h
 *  - Limites legais CLT: máx. 2h/dia e 44h semanais (verificar estatuto para efetivos)
 */
return new class extends Migration {
    public function up(): void
    {
        // ── HORA_EXTRA ─────────────────────────────────────────────────────────
        if (!Schema::hasTable('HORA_EXTRA')) {
            Schema::create('HORA_EXTRA', function (Blueprint $table) {
                $table->increments('HORA_EXTRA_ID');
                $table->unsignedInteger('FUNCIONARIO_ID');
                $table->unsignedInteger('UNIDADE_ID')->nullable();  // secretaria
                $table->unsignedInteger('SETOR_ID')->nullable();
                $table->string('COMPETENCIA', 7);  // AAAA-MM
                $table->date('DATA_REALIZACAO');
                $table->time('HORA_INICIO')->nullable();
                $table->time('HORA_FIM')->nullable();
                $table->decimal('TOTAL_HORAS', 5, 2);  // ex: 2.50 = 2h30min
                // 50_PORCENTO | 100_PORCENTO | FERIADO
                $table->string('TIPO_HORA_EXTRA', 20)->default('50_PORCENTO');
                $table->decimal('PERCENTUAL', 5, 2)->default(50.00);
                // Valor da hora simples calculado no momento do lançamento
                $table->decimal('VALOR_HORA_BASE', 10, 2)->default(0);
                // Valor final = hora_base × (1 + percentual/100) × total_horas
                $table->decimal('VALOR_CALCULADO', 15, 2)->default(0);
                $table->unsignedInteger('AUTORIZADO_POR')->nullable();  // USUARIO_ID gestor
                // PENDENTE | APROVADA | REJEITADA | INCLUIDA_FOLHA | PAGA
                $table->string('STATUS', 20)->default('PENDENTE');
                $table->text('OBSERVACAO')->nullable();
                $table->timestamps();
            });
        }

        // ── PLANTAO_EXTRA ──────────────────────────────────────────────────────
        if (!Schema::hasTable('PLANTAO_EXTRA')) {
            Schema::create('PLANTAO_EXTRA', function (Blueprint $table) {
                $table->increments('PLANTAO_EXTRA_ID');
                $table->unsignedInteger('FUNCIONARIO_ID');
                $table->unsignedInteger('UNIDADE_ID')->nullable();
                $table->unsignedInteger('SETOR_ID')->nullable();
                $table->string('COMPETENCIA', 7);
                $table->date('DATA_PLANTAO');
                $table->time('HORA_INICIO')->nullable();
                $table->time('HORA_FIM')->nullable();
                $table->decimal('TOTAL_HORAS', 5, 2);
                // Valor base da hora de plantão (pode ser diferente da hora extra)
                $table->decimal('VALOR_HORA_PLANTAO', 10, 2)->default(0);
                // Horas entre 22h e 05h: adicional de 20%
                $table->boolean('ADICIONAL_NOTURNO')->default(false);
                $table->decimal('HORAS_NOTURNAS', 5, 2)->default(0);
                $table->decimal('VALOR_ADICIONAL_NOTURNO', 10, 2)->default(0);
                $table->decimal('VALOR_CALCULADO', 15, 2)->default(0);
                $table->unsignedInteger('AUTORIZADO_POR')->nullable();
                // PENDENTE | APROVADO | REJEITADO | INCLUIDO_FOLHA | PAGO
                $table->string('STATUS', 20)->default('PENDENTE');
                $table->text('OBSERVACAO')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('PLANTAO_EXTRA');
        Schema::dropIfExists('HORA_EXTRA');
    }
};

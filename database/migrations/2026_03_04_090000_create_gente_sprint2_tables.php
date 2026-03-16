<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cria tabelas necessárias para os Sprints 2, 3 e 4 do GENTE v3.
 * Todas usam Schema::hasTable() — não quebra se já existir.
 */
return new class extends Migration {
    public function up()
    {
        // ── SUBSTITUICAO (substituição de plantão entre funcionários) ──────
        if (!Schema::hasTable('SUBSTITUICAO')) {
            Schema::create('SUBSTITUICAO', function (Blueprint $table) {
                $table->integer('SUBSTITUICAO_ID')->autoIncrement();
                $table->integer('SOLICITANTE_FUNCIONARIO_ID')->nullable();
                $table->integer('SUBSTITUTO_FUNCIONARIO_ID')->nullable();
                $table->integer('ESCALA_ID')->nullable();
                $table->date('SUBSTITUICAO_DATA')->nullable();
                $table->string('SUBSTITUICAO_TURNO', 50)->nullable();
                // Status: pendente / aprovada / reprovada
                $table->string('SUBSTITUICAO_STATUS', 30)->default('pendente');
                $table->text('SUBSTITUICAO_MOTIVO')->nullable();
                $table->date('SUBSTITUICAO_DT_SOLICITACAO')->nullable();
                $table->integer('APROVADOR_USUARIO_ID')->nullable();
                $table->date('SUBSTITUICAO_DT_APROVACAO')->nullable();
            });
        }

        // ── EXAME_MEDICO (medicina do trabalho) ────────────────────────────
        if (!Schema::hasTable('EXAME_MEDICO')) {
            Schema::create('EXAME_MEDICO', function (Blueprint $table) {
                $table->integer('EXAME_ID')->autoIncrement();
                $table->integer('FUNCIONARIO_ID');
                // Periódico, Admissional, Demissional, Audiometria, etc.
                $table->string('EXAME_TIPO', 100)->nullable();
                $table->date('EXAME_DATA_REALIZACAO')->nullable();
                $table->date('EXAME_DATA_VENCIMENTO')->nullable();
                // 1 = Apto, 0 = Inapto
                $table->integer('EXAME_APTO')->default(1);
                $table->string('EXAME_MEDICO', 200)->nullable();
                $table->string('EXAME_CRM', 30)->nullable();
                $table->text('EXAME_OBS')->nullable();
                // agendado / realizado / cancelado
                $table->string('EXAME_STATUS', 30)->default('realizado');
                $table->string('EXAME_ARQUIVO', 500)->nullable(); // caminho do laudo
            });
        }

        // ── PLANTAO_EXTRA (plantões extras / sobreaviso acionado) ─────────
        if (!Schema::hasTable('PLANTAO_EXTRA')) {
            Schema::create('PLANTAO_EXTRA', function (Blueprint $table) {
                $table->integer('PLANTAO_ID')->autoIncrement();
                $table->integer('FUNCIONARIO_ID');
                $table->integer('SETOR_ID')->nullable();
                $table->integer('ESCALA_ID')->nullable();
                $table->date('PLANTAO_DATA');
                // Diurno / Noturno / Integral
                $table->string('PLANTAO_TURNO', 50)->nullable();
                $table->decimal('PLANTAO_HORAS', 5, 2)->default(12);
                // pendente / aprovado / reprovado / pago
                $table->string('PLANTAO_STATUS', 30)->default('pendente');
                $table->text('PLANTAO_MOTIVO')->nullable();
            });
        }

        // ── ESCALA_SOBREAVISO (escala de sobreaviso) ──────────────────────
        if (!Schema::hasTable('ESCALA_SOBREAVISO')) {
            Schema::create('ESCALA_SOBREAVISO', function (Blueprint $table) {
                $table->integer('SOBREAVISO_ID')->autoIncrement();
                $table->integer('FUNCIONARIO_ID');
                $table->integer('SETOR_ID')->nullable();
                $table->date('SOBREAVISO_DATA');
                $table->string('SOBREAVISO_TURNO', 50)->nullable();
                $table->decimal('SOBREAVISO_HORAS', 5, 2)->default(12);
            });
        }

        // ── ACIONAMENTO_SOBREAVISO ──────────────────────────────────────────
        if (!Schema::hasTable('ACIONAMENTO_SOBREAVISO')) {
            Schema::create('ACIONAMENTO_SOBREAVISO', function (Blueprint $table) {
                $table->integer('ACIONAMENTO_ID')->autoIncrement();
                $table->integer('FUNCIONARIO_ID')->nullable();
                $table->date('ACIONAMENTO_DATA');
                $table->string('ACIONAMENTO_HORA', 10)->nullable();
                $table->text('ACIONAMENTO_MOTIVO')->nullable();
            });
        }

        // ── MANIFESTACAO (ouvidoria) ───────────────────────────────────────
        if (!Schema::hasTable('MANIFESTACAO')) {
            Schema::create('MANIFESTACAO', function (Blueprint $table) {
                $table->integer('MANIFESTACAO_ID')->autoIncrement();
                $table->integer('USUARIO_ID')->nullable();
                // Sugestão / Reclamação / Elogio / Denúncia
                $table->string('MANIFESTACAO_TIPO', 50)->nullable();
                $table->string('MANIFESTACAO_ASSUNTO', 300)->nullable();
                $table->text('MANIFESTACAO_DESCRICAO')->nullable();
                $table->integer('MANIFESTACAO_ANONIMA')->default(0);
                // aberta / em_analise / respondida / arquivada
                $table->string('MANIFESTACAO_STATUS', 30)->default('aberta');
                $table->string('MANIFESTACAO_PROTOCOLO', 30)->nullable();
                $table->date('MANIFESTACAO_DT_REGISTRO')->nullable();
                $table->text('MANIFESTACAO_RESPOSTA')->nullable();
                $table->date('MANIFESTACAO_DT_RESPOSTA')->nullable();
                $table->integer('RESPONDEDOR_USUARIO_ID')->nullable();
            });
        }

        // ── DECLARACAO (declarações / requerimentos) ──────────────────────
        if (!Schema::hasTable('DECLARACAO')) {
            Schema::create('DECLARACAO', function (Blueprint $table) {
                $table->integer('DECLARACAO_ID')->autoIncrement();
                $table->integer('FUNCIONARIO_ID')->nullable();
                // Declaração de Vínculo / Tempo de Serviço / Férias / etc.
                $table->string('DECLARACAO_TIPO', 100)->nullable();
                // pendente / processando / pronto / cancelado
                $table->string('DECLARACAO_STATUS', 30)->default('pendente');
                $table->text('DECLARACAO_OBS')->nullable();
                $table->date('DECLARACAO_DT_SOLICITACAO')->nullable();
                $table->date('DECLARACAO_DT_CONCLUSAO')->nullable();
                $table->string('DECLARACAO_ARQUIVO', 500)->nullable(); // PDF gerado
            });
        }

        // ── AGENDA_EVENTO (agenda institucional) ──────────────────────────
        if (!Schema::hasTable('AGENDA_EVENTO')) {
            Schema::create('AGENDA_EVENTO', function (Blueprint $table) {
                $table->integer('EVENTO_ID')->autoIncrement();
                $table->string('EVENTO_TITULO', 300);
                $table->date('EVENTO_DATA');
                $table->time('EVENTO_HORA_INICIO')->nullable();
                $table->time('EVENTO_HORA_FIM')->nullable();
                // reuniao / treinamento / feriado / prazo / outro
                $table->string('EVENTO_TIPO', 50)->nullable();
                $table->text('EVENTO_DESCRICAO')->nullable();
                $table->string('EVENTO_LOCAL', 300)->nullable();
                $table->integer('USUARIO_ID')->nullable();
                $table->integer('SETOR_ID')->nullable();
                $table->integer('EVENTO_PUBLICO')->default(1); // 1 = visível a todos
            });
        }

        // ── NOTIFICACAO (sistema de notificações in-app) ──────────────────
        if (!Schema::hasTable('NOTIFICACAO')) {
            Schema::create('NOTIFICACAO', function (Blueprint $table) {
                $table->integer('NOTIFICACAO_ID')->autoIncrement();
                $table->integer('USUARIO_ID');
                $table->string('NOTIFICACAO_TITULO', 200);
                $table->text('NOTIFICACAO_BODY')->nullable();
                // info / success / warning / error
                $table->string('NOTIFICACAO_TIPO', 20)->default('info');
                $table->string('NOTIFICACAO_ICONE', 10)->nullable(); // emoji
                $table->string('NOTIFICACAO_URL', 300)->nullable(); // link de ação
                $table->integer('NOTIFICACAO_LIDA')->default(0);
                $table->dateTime('NOTIFICACAO_DT_CRIACAO')->nullable();
                $table->dateTime('NOTIFICACAO_DT_LEITURA')->nullable();
            });
        }

        // ── COMUNICADO_LEITURA (rastreamento de leitura de comunicados) ───
        if (!Schema::hasTable('COMUNICADO_LEITURA')) {
            Schema::create('COMUNICADO_LEITURA', function (Blueprint $table) {
                $table->integer('LEITURA_ID')->autoIncrement();
                $table->integer('COMUNICADO_ID');
                $table->integer('USUARIO_ID');
                $table->dateTime('LEITURA_DT')->nullable();
            });
        }
    }

    public function down()
    {
        // No-op intencional — não derrubamos tabelas em produção
    }
};

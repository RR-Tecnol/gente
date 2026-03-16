<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cria a tabela de rastreamento de eventos eSocial.
 *
 * ESOCIAL_EVENTO — fila de eventos gerados pelo sistema para envio ao gov.br
 *
 * Eventos suportados nesta versão:
 *   S-2200 — Admissão / Cadastramento Inicial
 *   S-2206 — Alteração de Contrato (cargo, salário, lotação)
 *   S-2230 — Afastamento Temporário
 *   S-2240 — Condições Ambientais de Trabalho
 *   S-2299 — Desligamento
 *   S-1200 — Remuneração do Trabalhador
 *   S-1210 — Pagamentos de Rendimentos
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('ESOCIAL_EVENTO')) {
            Schema::create('ESOCIAL_EVENTO', function (Blueprint $table) {
                $table->increments('EVENTO_ID');
                $table->unsignedInteger('FUNCIONARIO_ID');
                // S-2200 | S-2206 | S-2230 | S-2299 | S-1200 | S-1210 | S-2240
                $table->string('TIPO_EVENTO', 10);
                $table->string('COMPETENCIA', 7)->nullable();    // AAAA-MM (para S-1200)
                $table->date('DATA_REFERENCIA')->nullable();     // data do fato gerador
                // PENDENTE | GERADO | ENVIADO | PROCESSADO | REJEITADO | EXCLUIDO
                $table->string('STATUS', 20)->default('PENDENTE');
                $table->string('NUMERO_RECIBO', 50)->nullable(); // retorno do gov.br
                $table->text('XML_GERADO')->nullable();          // payload XML
                $table->text('RETORNO_GOVERNO')->nullable();     // JSON de resposta
                $table->text('MOTIVO_ERRO')->nullable();
                $table->datetime('DT_ENVIO')->nullable();
                $table->datetime('DT_PROCESSAMENTO')->nullable();
                $table->unsignedInteger('GERADO_POR')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ESOCIAL_EVENTO');
    }
};

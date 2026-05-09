<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * GENTE v3 — Patch idempotente: adiciona VINCULO_ID em FUNCIONARIO.
 *
 * CONTEXTO ARQUITETURAL:
 *   Em PMSL legacy, o vínculo trabalhista do servidor é registrado em LOTACAO.VINCULO_ID
 *   (cada lotação tem seu vínculo, permitindo histórico de mudanças). Mas o
 *   MotorFolhaService::calcularLoteParaFuncionarios() faz:
 *
 *     leftJoin('VINCULO as v', 'v.VINCULO_ID', '=', 'f.VINCULO_ID')
 *
 *   …assumindo que FUNCIONARIO tem VINCULO_ID denormalizado. Em produção PMSL
 *   essa coluna não existe e o motor quebra com SQLSTATE[42S22].
 *
 * SOLUÇÃO PRAGMÁTICA:
 *   Adicionar VINCULO_ID nullable em FUNCIONARIO. Servidores que tiverem o campo
 *   preenchido (via demo seeder ou pipeline de cadastro futuro) usam diretamente.
 *   Servidores reais legados continuam com NULL — leftJoin funciona, motor cai no
 *   fallback `$s->VINCULO_TIPO ?? 'efetivo'` na linha 357 do MotorFolhaService.
 *
 * DÍVIDA TÉCNICA (pós go-live):
 *   Refatorar MotorFolhaService::calcularLoteParaFuncionarios() para resolver
 *   VINCULO_ID via LOTACAO.VINCULO_ID (lotação ativa para a competência),
 *   não FUNCIONARIO.VINCULO_ID. Aí esta coluna pode ser removida.
 *   Ver docs/DIVIDA_TECNICA_POS_GOLIVE_PMSL.md (DT-MOTOR-01).
 */
return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('FUNCIONARIO')) {
            return;
        }
        if (Schema::hasColumn('FUNCIONARIO', 'VINCULO_ID')) {
            return;
        }

        Schema::table('FUNCIONARIO', function (Blueprint $table) {
            $table->unsignedInteger('VINCULO_ID')->nullable()->default(null);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('FUNCIONARIO')) {
            return;
        }
        if (! Schema::hasColumn('FUNCIONARIO', 'VINCULO_ID')) {
            return;
        }

        Schema::table('FUNCIONARIO', function (Blueprint $table) {
            $table->dropColumn('VINCULO_ID');
        });
    }
};

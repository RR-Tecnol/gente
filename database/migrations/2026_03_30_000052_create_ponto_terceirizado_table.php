<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Adicionar timestamps à TERCEIRO_POSTO se não existir
        if (Schema::hasTable('TERCEIRO_POSTO')) {
            Schema::table('TERCEIRO_POSTO', function (Blueprint $table) {
                if (!Schema::hasColumn('TERCEIRO_POSTO', 'created_at')) {
                    $table->timestamps();
                }
                if (!Schema::hasColumn('TERCEIRO_POSTO', 'POSTO_ATIVO')) {
                    $table->boolean('POSTO_ATIVO')->default(true)->after('TRABALHADOR_CPF');
                }
            });
        }

        // Registro de frequência diária por posto
        if (!Schema::hasTable('TERCEIRO_FREQUENCIA')) {
            Schema::create('TERCEIRO_FREQUENCIA', function (Blueprint $table) {
                $table->increments('FREQ_ID');
                $table->unsignedInteger('POSTO_ID');
                $table->unsignedInteger('EMPRESA_ID');
                $table->string('TRABALHADOR_CPF', 11)->nullable();
                $table->string('TRABALHADOR_NOME', 150)->nullable();
                $table->date('FREQ_DATA');
                $table->string('FREQ_COMPETENCIA', 6); // AAAAMM
                // PRESENTE | AUSENTE | FALTA_JUSTIFICADA | AFASTADO | FOLGA
                $table->string('FREQ_STATUS', 20)->default('PRESENTE');
                $table->time('FREQ_ENTRADA')->nullable();
                $table->time('FREQ_SAIDA')->nullable();
                // Horas efetivas trabalhadas (calculado)
                $table->decimal('FREQ_HORAS', 5, 2)->default(0);
                $table->string('FREQ_OBSERVACAO', 300)->nullable();
                $table->unsignedInteger('REGISTRADO_POR')->nullable();
                $table->timestamps();

                $table->unique(['POSTO_ID', 'FREQ_DATA'], 'uq_freq_posto_data');
                $table->index(['FREQ_COMPETENCIA', 'EMPRESA_ID']);
            });
        }

        // Apuração mensal por empresa — base para glosa e pagamento
        if (!Schema::hasTable('TERCEIRO_APURACAO')) {
            Schema::create('TERCEIRO_APURACAO', function (Blueprint $table) {
                $table->increments('APURACAO_ID');
                $table->unsignedInteger('EMPRESA_ID');
                $table->string('APURACAO_COMPETENCIA', 6); // AAAAMM
                $table->integer('TOTAL_POSTOS')->default(0);
                $table->integer('TOTAL_PRESENCAS')->default(0);
                $table->integer('TOTAL_FALTAS')->default(0);
                $table->decimal('PCT_PRESENCA', 5, 2)->default(0);
                // Valor a pagar após glosas por faltas
                $table->decimal('VALOR_CONTRATO', 15, 2)->default(0);
                $table->decimal('VALOR_GLOSA', 15, 2)->default(0);
                $table->decimal('VALOR_PAGAR', 15, 2)->default(0);
                // ABERTA | FECHADA | PAGA
                $table->string('APURACAO_STATUS', 20)->default('ABERTA');
                $table->unsignedInteger('FECHADO_POR')->nullable();
                $table->timestamps();

                $table->unique(['EMPRESA_ID', 'APURACAO_COMPETENCIA'], 'uq_apuracao_empresa_comp');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('TERCEIRO_APURACAO');
        Schema::dropIfExists('TERCEIRO_FREQUENCIA');
    }
};

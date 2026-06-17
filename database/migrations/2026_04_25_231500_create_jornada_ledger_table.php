<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('JORNADA_LEDGER')) {
            Schema::create('JORNADA_LEDGER', function (Blueprint $table) {
                $table->bigIncrements('JORNADA_LEDGER_ID');
                $table->unsignedBigInteger('FUNCIONARIO_ID');
                $table->char('COMPETENCIA', 7); // YYYY-MM
                $table->date('JORNADA_DATA');
                $table->string('LANCAMENTO_TIPO', 20)->default('ajuste'); // credito|debito|ajuste
                $table->integer('MINUTOS_TRABALHADOS')->default(0);
                $table->integer('MINUTOS_META')->default(0);
                $table->integer('MINUTOS_DELTA')->default(0); // positivo=extra, negativo=falta
                $table->decimal('HORAS_CREDITADAS', 8, 2)->default(0);
                $table->decimal('HORAS_DEBITADAS', 8, 2)->default(0);
                $table->decimal('SALDO_HORAS', 8, 2)->default(0);
                $table->integer('VERSAO')->default(1);
                $table->string('ORIGEM', 40)->default('ponto_web');
                $table->string('MOTIVO', 120)->nullable();
                $table->text('DETALHE')->nullable();
                $table->string('HASH_AUDITORIA', 64)->nullable();
                $table->unsignedBigInteger('GERADO_POR_USUARIO_ID')->nullable();
                $table->unsignedBigInteger('ANULA_LEDGER_ID')->nullable();
                $table->dateTime('GERADO_EM')->nullable();
                $table->dateTime('created_at')->nullable();
                $table->dateTime('updated_at')->nullable();

                $table->index(['FUNCIONARIO_ID', 'COMPETENCIA'], 'idx_led_func_comp');
                $table->index(['FUNCIONARIO_ID', 'JORNADA_DATA'], 'idx_led_func_data');
                $table->unique(['FUNCIONARIO_ID', 'JORNADA_DATA', 'VERSAO'], 'uk_led_func_data_versao');
            });
        }
    }

    public function down(): void
    {
        // No-op intencional (sem drop em rollback em ambiente partilhado)
    }
};

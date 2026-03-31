<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('SICONFI_RELATORIO')) {
            Schema::create('SICONFI_RELATORIO', function (Blueprint $table) {
                $table->increments('RELATORIO_ID');
                $table->integer('RELATORIO_ANO');
                // RREO | RGF
                $table->string('RELATORIO_TIPO', 10);
                // Bimestre (1-6) para RREO | Quadrimestre (1-3) para RGF
                $table->integer('RELATORIO_PERIODO');
                $table->string('RELATORIO_STATUS', 20)->default('GERADO');
                $table->decimal('RCL_VALOR', 15, 2)->default(0);
                $table->decimal('DESPESA_PESSOAL_TOTAL', 15, 2)->default(0);
                $table->decimal('DESPESA_PESSOAL_PCT', 8, 4)->default(0);
                $table->text('RELATORIO_JSON')->nullable();
                $table->string('RELATORIO_ARQUIVO_NOME', 200)->nullable();
                $table->unsignedInteger('GERADO_POR')->nullable();
                $table->timestamps();

                $table->unique(['RELATORIO_ANO', 'RELATORIO_TIPO', 'RELATORIO_PERIODO'],
                    'uq_siconfi_periodo');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('SICONFI_RELATORIO');
    }
};

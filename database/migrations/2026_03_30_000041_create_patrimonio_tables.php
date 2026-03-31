<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('BEM_PATRIMONIAL')) {
            Schema::create('BEM_PATRIMONIAL', function (Blueprint $table) {
                $table->increments('BEM_ID');
                $table->string('BEM_NUMERO', 30)->unique(); // número de tombamento
                $table->string('BEM_DESCRICAO', 300);
                // IMOVEL | MOVEL | EQUIPAMENTO | VEICULO | TI
                $table->string('BEM_CATEGORIA', 50);
                $table->decimal('BEM_VALOR_AQUISICAO', 15, 2);
                $table->date('BEM_DATA_AQUISICAO');
                $table->decimal('BEM_VALOR_ATUAL', 15, 2)->nullable();
                // OTIMO | BOM | REGULAR | RUIM | INSERVIVEL
                $table->string('BEM_ESTADO', 20)->default('BOM');
                // ATIVO | BAIXADO | CEDIDO | EM_MANUTENCAO
                $table->string('BEM_STATUS', 20)->default('ATIVO');
                $table->unsignedInteger('UO_ID')->nullable();
                $table->unsignedInteger('SERVIDOR_ID')->nullable();
                // Campos de depreciação NBCASP 16.9
                $table->decimal('BEM_VIDA_UTIL_ANOS', 5, 1)->default(10);
                $table->decimal('BEM_VALOR_RESIDUAL', 15, 2)->default(0);
                $table->decimal('BEM_DEPRECIACAO_ACUMULADA', 15, 2)->default(0);
                $table->date('BEM_DATA_ULTIMA_DEPRECIACAO')->nullable();
                $table->timestamps();

                $table->index(['BEM_STATUS', 'BEM_CATEGORIA']);
                $table->index(['UO_ID']);
            });
        }

        if (!Schema::hasTable('MOVIMENTACAO_PATRIMONIAL')) {
            Schema::create('MOVIMENTACAO_PATRIMONIAL', function (Blueprint $table) {
                $table->increments('MOV_ID');
                $table->unsignedInteger('BEM_ID');
                // TRANSFERENCIA | BAIXA | EMPRESTIMO | DEVOLUCAO | MANUTENCAO
                $table->string('MOV_TIPO', 20);
                $table->unsignedInteger('UO_ORIGEM_ID')->nullable();
                $table->unsignedInteger('UO_DESTINO_ID')->nullable();
                $table->text('MOV_MOTIVO')->nullable();
                $table->date('MOV_DATA');
                $table->unsignedInteger('REGISTRADO_POR')->nullable();
                $table->timestamps();

                $table->index(['BEM_ID']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('MOVIMENTACAO_PATRIMONIAL');
        Schema::dropIfExists('BEM_PATRIMONIAL');
    }
};

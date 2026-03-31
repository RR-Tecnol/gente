<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Adicionar RUBRICA_SISTEMA em SAGRES_EVENTO_DEPARA
        // (tabela existe mas route usa RUBRICA_SISTEMA, coluna atual é EVENTO_INTERNO_COD)
        Schema::table('SAGRES_EVENTO_DEPARA', function (Blueprint $table) {
            if (!Schema::hasColumn('SAGRES_EVENTO_DEPARA', 'RUBRICA_SISTEMA')) {
                $table->string('RUBRICA_SISTEMA', 20)->nullable()->after('DEPARA_ID');
            }
        });

        // SAGRES_GERACAO — histórico de arquivos gerados (route usa este nome,
        // diferente de SAGRES_EXPORTACAO que está na migration original)
        if (!Schema::hasTable('SAGRES_GERACAO')) {
            Schema::create('SAGRES_GERACAO', function (Blueprint $table) {
                $table->increments('GERACAO_ID');
                $table->string('COMPETENCIA', 7);        // ex: 2026-03
                $table->string('ARQUIVO_NOME', 200)->nullable();
                $table->unsignedInteger('TOTAL_SERV')->default(0);
                $table->decimal('TOTAL_LIQUIDO', 15, 2)->default(0);
                $table->unsignedInteger('GERADO_POR')->nullable(); // FK USUARIO
                // GERADO | ENVIADO | ACEITO | REJEITADO
                $table->string('STATUS', 20)->default('GERADO');
                $table->timestamps();

                $table->index(['COMPETENCIA']);
            });
        }
    }

    public function down(): void
    {
        Schema::table('SAGRES_EVENTO_DEPARA', function (Blueprint $table) {
            $table->dropColumn('RUBRICA_SISTEMA');
        });
        Schema::dropIfExists('SAGRES_GERACAO');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // LAYOUT_CONSIGNATARIA — campos operacionais do parser
        Schema::table('LAYOUT_CONSIGNATARIA', function (Blueprint $table) {
            if (!Schema::hasColumn('LAYOUT_CONSIGNATARIA', 'LAYOUT_NOME')) {
                $table->string('LAYOUT_NOME', 50)->nullable()->after('CONSIGNATARIA_ID');
                // Ex: "NEOCONSIG_DEBITOS", "NEOCONSIG_FINANCEIRO"
            }
            if (!Schema::hasColumn('LAYOUT_CONSIGNATARIA', 'LAYOUT_DIRECAO')) {
                $table->string('LAYOUT_DIRECAO', 10)->default('ENTRADA')->after('LAYOUT_NOME');
                // ENTRADA = arquivo recebido da operadora | SAIDA = arquivo gerado pelo sistema
            }
            if (!Schema::hasColumn('LAYOUT_CONSIGNATARIA', 'LAYOUT_TAMANHO_LINHA')) {
                $table->unsignedSmallInteger('LAYOUT_TAMANHO_LINHA')->nullable()->after('LAYOUT_DIRECAO');
                // Neoconsig: DEBITOS=115, FINANCEIRO=66, CADASTRO=523
            }
            if (!Schema::hasColumn('LAYOUT_CONSIGNATARIA', 'LAYOUT_ENCODING')) {
                $table->string('LAYOUT_ENCODING', 20)->default('UTF-8')->after('LAYOUT_TAMANHO_LINHA');
                // UTF-8 ou ISO-8859-1 (Latin-1) — crítico para acentuação
            }
        });

        // CONSIG_REMESSA — campos de auditoria de processamento
        Schema::table('CONSIG_REMESSA', function (Blueprint $table) {
            if (!Schema::hasColumn('CONSIG_REMESSA', 'REMESSA_ERROS')) {
                $table->unsignedInteger('REMESSA_ERROS')->default(0)->after('REMESSA_TOTAL_VALOR');
                // Contador de linhas rejeitadas no processamento
            }
            if (!Schema::hasColumn('CONSIG_REMESSA', 'REMESSA_LOG')) {
                $table->text('REMESSA_LOG')->nullable()->after('REMESSA_ERROS');
                // Log detalhado linha a linha dos erros encontrados
            }
        });
    }

    public function down(): void
    {
        Schema::table('LAYOUT_CONSIGNATARIA', function (Blueprint $table) {
            $table->dropColumn([
                'LAYOUT_NOME', 'LAYOUT_DIRECAO',
                'LAYOUT_TAMANHO_LINHA', 'LAYOUT_ENCODING'
            ]);
        });

        Schema::table('CONSIG_REMESSA', function (Blueprint $table) {
            $table->dropColumn(['REMESSA_ERROS', 'REMESSA_LOG']);
        });
    }
};

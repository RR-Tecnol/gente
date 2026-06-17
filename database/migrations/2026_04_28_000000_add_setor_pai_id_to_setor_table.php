<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hierarquia de setores (cadeia de comando): SETOR_PAI_ID opcional.
 * Sem a coluna, o escopo continua ancorado em USUARIO_SETOR / lotação sem expansão por árvore.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('SETOR')) {
            return;
        }
        if (Schema::hasColumn('SETOR', 'SETOR_PAI_ID')) {
            return;
        }
        Schema::table('SETOR', function (Blueprint $table) {
            $table->integer('SETOR_PAI_ID')->nullable()->after('UNIDADE_ID');
            $table->index('SETOR_PAI_ID');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('SETOR') || ! Schema::hasColumn('SETOR', 'SETOR_PAI_ID')) {
            return;
        }
        Schema::table('SETOR', function (Blueprint $table) {
            $table->dropColumn('SETOR_PAI_ID');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Domínio canónico de PCCV (Planos de Cargos, Carreiras e Vencimentos) — base para folha e vínculo futuro em CARGO.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('PCCV_DOMINIO')) {
            return;
        }

        Schema::create('PCCV_DOMINIO', function (Blueprint $table) {
            $table->increments('PCCV_DOMINIO_ID');
            $table->string('NOME_LEI', 200);
            $table->string('SIGLA', 40)->nullable();
            $table->boolean('ATIVO')->default(true);
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('PCCV_DOMINIO')) {
            Schema::drop('PCCV_DOMINIO');
        }
    }
};

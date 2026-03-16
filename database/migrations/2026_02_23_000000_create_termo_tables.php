<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cria tabelas de Termos de Uso do sistema.
 * TERMO: documentos de aceite (LGPD, regulamentos, etc.)
 * TERMO_USUARIO: registro de quem aceitou qual termo e quando.
 *
 * Consultadas pelo CompartilharVariaveis em todo request autenticado.
 */
class CreateTermoTables extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('TERMO')) {
            Schema::create('TERMO', function (Blueprint $table) {
                $table->integer('TERMO_ID')->autoIncrement();
                $table->string('TERMO_NOME', 200);
                $table->string('TERMO_ARQUIVO', 500)->nullable();
                $table->string('TERMO_EXTENSAO', 20)->nullable();
                $table->integer('TERMO_ATIVO')->default(1);
            });
        }

        if (!Schema::hasTable('TERMO_USUARIO')) {
            Schema::create('TERMO_USUARIO', function (Blueprint $table) {
                $table->integer('TERMO_USUARIO_ID')->autoIncrement();
                $table->integer('TERMO_ID');
                $table->integer('USUARIO_ID');
                $table->dateTime('TERMO_USUARIO_DATA')->nullable();
            });
        }
    }

    public function down()
    {
        // No-op intencional
    }
}

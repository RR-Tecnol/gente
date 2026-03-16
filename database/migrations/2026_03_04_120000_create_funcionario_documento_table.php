<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        if (!Schema::hasTable('FUNCIONARIO_DOCUMENTO')) {
            Schema::create('FUNCIONARIO_DOCUMENTO', function (Blueprint $table) {
                $table->integer('DOC_ID')->autoIncrement();
                $table->integer('FUNCIONARIO_ID');
                // RG, CPF, CNH, Diploma, PIS/PASEP, Título de Eleitor, etc.
                $table->string('DOC_TIPO', 100)->nullable();
                $table->string('DOC_NUMERO', 100)->nullable();
                // Caminho relativo no storage public (ex: documentos/12/abc123.pdf)
                $table->string('DOC_ARQUIVO', 500)->nullable();
                $table->integer('DOC_TAMANHO')->nullable(); // bytes
                $table->integer('DOC_OBRIGATORIO')->default(0);
                $table->dateTime('DOC_DT_UPLOAD')->nullable();
                $table->integer('ENVIADO_USUARIO_ID')->nullable();
            });
        }
    }

    public function down()
    {
        // No-op — não derrubamos tabelas em produção
    }
};

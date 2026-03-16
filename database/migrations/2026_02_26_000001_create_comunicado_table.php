<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('COMUNICADO')) {
            Schema::create('COMUNICADO', function (Blueprint $table) {
                $table->id('COMUNICADO_ID');
                $table->string('TITULO', 255);
                $table->text('CONTEUDO');
                $table->string('CATEGORIA', 50)->default('rh');
                $table->string('PRIORIDADE', 20)->default('normal');
                $table->tinyInteger('FIXADO')->default(0);
                $table->tinyInteger('ATIVO')->default(1);
                $table->unsignedBigInteger('USUARIO_ID')->nullable();
                $table->string('AUTOR_NOME', 200)->nullable();
                $table->string('AUTOR_SETOR', 200)->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('COMUNICADO');
    }
};

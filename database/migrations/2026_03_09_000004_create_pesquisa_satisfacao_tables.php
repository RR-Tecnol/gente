<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Pesquisas criadas pelo RH
        if (!Schema::hasTable('PESQUISA_SATISFACAO')) {
            Schema::create('PESQUISA_SATISFACAO', function (Blueprint $table) {
                $table->increments('PESQUISA_ID');
                $table->string('PESQUISA_TITULO', 200);
                $table->text('PESQUISA_DESC')->nullable();
                $table->string('PESQUISA_STATUS', 20)->default('aberta'); // aberta | encerrada
                $table->date('PESQUISA_FIM')->nullable();
                $table->unsignedInteger('CRIADOR_ID')->nullable();
                $table->timestamps();
            });
        }

        // Perguntas de cada pesquisa
        if (!Schema::hasTable('PESQUISA_PERGUNTA')) {
            Schema::create('PESQUISA_PERGUNTA', function (Blueprint $table) {
                $table->increments('PERGUNTA_ID');
                $table->unsignedInteger('PESQUISA_ID')->index();
                $table->string('PERGUNTA_TEXTO', 300);
                $table->string('PERGUNTA_TIPO', 20)->default('nota'); // nota(1-5) | texto
                $table->tinyInteger('PERGUNTA_ORDEM')->default(1);
                $table->timestamps();
            });
        }

        // Respostas (anônimas por padrão)
        if (!Schema::hasTable('PESQUISA_RESPOSTA')) {
            Schema::create('PESQUISA_RESPOSTA', function (Blueprint $table) {
                $table->increments('RESPOSTA_ID');
                $table->unsignedInteger('PESQUISA_ID')->index();
                $table->unsignedInteger('PERGUNTA_ID')->index();
                $table->unsignedInteger('FUNCIONARIO_ID')->nullable(); // null = anônimo
                $table->tinyInteger('RESPOSTA_NOTA')->nullable();       // 1-5
                $table->text('RESPOSTA_TEXTO')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('PESQUISA_RESPOSTA');
        Schema::dropIfExists('PESQUISA_PERGUNTA');
        Schema::dropIfExists('PESQUISA_SATISFACAO');
    }
};

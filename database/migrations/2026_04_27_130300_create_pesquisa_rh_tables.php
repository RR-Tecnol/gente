<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('PESQUISA')) {
            Schema::create('PESQUISA', function (Blueprint $table) {
                $table->increments('PESQUISA_ID');
                $table->string('PESQUISA_TITULO', 200);
                $table->date('PESQUISA_DATA_INICIO')->nullable();
                $table->date('PESQUISA_DATA_FIM')->nullable();
                $table->string('PESQUISA_PUBLICO_ALVO', 100)->nullable();
                $table->boolean('PESQUISA_ATIVA')->default(true);
                $table->timestamps();
            });
        }
        if (!Schema::hasTable('PESQUISA_PERGUNTA')) {
            Schema::create('PESQUISA_PERGUNTA', function (Blueprint $table) {
                $table->increments('PERGUNTA_ID');
                $table->unsignedInteger('PESQUISA_ID');
                $table->string('PERGUNTA_TEXTO', 500);
                $table->string('PERGUNTA_TIPO', 50);
                $table->json('PERGUNTA_OPCOES')->nullable();
                $table->integer('PERGUNTA_ORDEM')->default(0);
                $table->timestamps();
            });
        }
        if (!Schema::hasTable('PESQUISA_RESPOSTA')) {
            Schema::create('PESQUISA_RESPOSTA', function (Blueprint $table) {
                $table->increments('RESPOSTA_ID');
                $table->unsignedInteger('PESQUISA_ID');
                $table->unsignedInteger('PERGUNTA_ID');
                $table->text('RESPOSTA_VALOR')->nullable();
                $table->string('SESSAO_TOKEN', 100)->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('PESQUISA_RESPOSTA')) {
            Schema::drop('PESQUISA_RESPOSTA');
        }
        if (Schema::hasTable('PESQUISA_PERGUNTA')) {
            Schema::drop('PESQUISA_PERGUNTA');
        }
        if (Schema::hasTable('PESQUISA')) {
            Schema::drop('PESQUISA');
        }
    }
};

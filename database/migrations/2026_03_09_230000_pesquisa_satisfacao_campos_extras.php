<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // ── Pesquisa ─────────────────────────────────────────────────────
        if (!Schema::hasTable('PESQUISA_SATISFACAO')) {
            Schema::create('PESQUISA_SATISFACAO', function (Blueprint $t) {
                $t->id('PESQUISA_ID');
                $t->string('PESQUISA_TITULO');
                $t->text('PESQUISA_DESC')->nullable();
                $t->string('PESQUISA_STATUS', 20)->default('rascunho'); // rascunho|aberta|encerrada
                $t->date('PESQUISA_INICIO')->nullable();
                $t->date('PESQUISA_FIM')->nullable();
                $t->unsignedBigInteger('CRIADO_POR')->nullable();
                $t->timestamps();
            });
        } else {
            // Garante coluna PESQUISA_STATUS
            if (!Schema::hasColumn('PESQUISA_SATISFACAO', 'PESQUISA_STATUS'))
                Schema::table('PESQUISA_SATISFACAO', fn($t) => $t->string('PESQUISA_STATUS', 20)->default('rascunho'));
            if (!Schema::hasColumn('PESQUISA_SATISFACAO', 'PESQUISA_INICIO'))
                Schema::table('PESQUISA_SATISFACAO', fn($t) => $t->date('PESQUISA_INICIO')->nullable());
            if (!Schema::hasColumn('PESQUISA_SATISFACAO', 'CRIADO_POR'))
                Schema::table('PESQUISA_SATISFACAO', fn($t) => $t->unsignedBigInteger('CRIADO_POR')->nullable());
        }

        // ── Pergunta ─────────────────────────────────────────────────────
        if (!Schema::hasTable('PESQUISA_PERGUNTA')) {
            Schema::create('PESQUISA_PERGUNTA', function (Blueprint $t) {
                $t->id('PERGUNTA_ID');
                $t->unsignedBigInteger('PESQUISA_ID');
                $t->string('PERGUNTA_TEXTO');
                $t->string('PERGUNTA_TIPO', 20)->default('nps'); // nps|estrelas|opcoes|texto
                $t->integer('PERGUNTA_ORDEM')->default(0);
                $t->json('PERGUNTA_OPCOES')->nullable();
                $t->timestamps();
            });
        } else {
            if (!Schema::hasColumn('PESQUISA_PERGUNTA', 'PERGUNTA_OPCOES'))
                Schema::table('PESQUISA_PERGUNTA', fn($t) => $t->json('PERGUNTA_OPCOES')->nullable());
        }

        // ── Resposta ─────────────────────────────────────────────────────
        if (!Schema::hasTable('PESQUISA_RESPOSTA')) {
            Schema::create('PESQUISA_RESPOSTA', function (Blueprint $t) {
                $t->id('RESPOSTA_ID');
                $t->unsignedBigInteger('PESQUISA_ID');
                $t->unsignedBigInteger('PERGUNTA_ID')->nullable();
                $t->unsignedBigInteger('FUNCIONARIO_ID')->nullable(); // null = anônimo
                $t->decimal('RESPOSTA_NOTA', 5, 2)->nullable();
                $t->text('RESPOSTA_TEXTO')->nullable();
                $t->timestamps();
            });
        }
    }

    public function down(): void
    {
    }
};

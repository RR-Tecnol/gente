<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('TREINAMENTO')) {
            Schema::create('TREINAMENTO', function (Blueprint $table) {
                $table->increments('TREINAMENTO_ID');
                $table->string('TREINAMENTO_TITULO', 200);
                $table->text('TREINAMENTO_DESC')->nullable();
                $table->string('TREINAMENTO_AREA', 100)->default('Geral');
                $table->integer('TREINAMENTO_CARGA')->default(0);
                $table->string('TREINAMENTO_MODALIDADE', 50)->default('EAD');
                $table->string('TREINAMENTO_PROXIMA', 100)->nullable();
                $table->integer('TREINAMENTO_VAGAS')->default(0);
                $table->boolean('TREINAMENTO_ATIVO')->default(true);
                $table->timestamps();
            });
        }
        if (!Schema::hasTable('TREINAMENTO_INSCRICAO')) {
            Schema::create('TREINAMENTO_INSCRICAO', function (Blueprint $table) {
                $table->increments('INSCRICAO_ID');
                $table->unsignedInteger('TREINAMENTO_ID')->index();
                $table->unsignedInteger('FUNCIONARIO_ID')->index();
                $table->string('INSCRICAO_STATUS', 30)->default('inscrito');
                $table->integer('INSCRICAO_PROGRESSO')->default(0);
                $table->boolean('INSCRICAO_CERTIFICADO')->default(false);
                $table->date('INSCRICAO_DATA_CONCLUSAO')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('TREINAMENTO_INSCRICAO')) {
            Schema::drop('TREINAMENTO_INSCRICAO');
        }
        if (Schema::hasTable('TREINAMENTO')) {
            Schema::drop('TREINAMENTO');
        }
    }
};

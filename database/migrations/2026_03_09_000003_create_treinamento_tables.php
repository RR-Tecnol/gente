<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Catálogo de treinamentos
        if (!Schema::hasTable('TREINAMENTO')) {
            Schema::create('TREINAMENTO', function (Blueprint $table) {
                $table->increments('TREINAMENTO_ID');
                $table->string('TREINAMENTO_TITULO', 200);
                $table->text('TREINAMENTO_DESC')->nullable();
                $table->string('TREINAMENTO_AREA', 60)->nullable();     // Saúde, Segurança, Tecnologia…
                $table->tinyInteger('TREINAMENTO_CARGA')->default(8);   // horas
                $table->string('TREINAMENTO_MODALIDADE', 30)->default('EAD'); // EAD | Presencial | Híbrido
                $table->string('TREINAMENTO_PROXIMA', 20)->nullable();  // "Mar/2026"
                $table->tinyInteger('TREINAMENTO_VAGAS')->default(30);
                $table->boolean('TREINAMENTO_ATIVO')->default(true);
                $table->timestamps();
            });
        }

        // Matrículas / inscrições do funcionário
        if (!Schema::hasTable('TREINAMENTO_INSCRICAO')) {
            Schema::create('TREINAMENTO_INSCRICAO', function (Blueprint $table) {
                $table->increments('INSCRICAO_ID');
                $table->unsignedInteger('TREINAMENTO_ID')->index();
                $table->unsignedInteger('FUNCIONARIO_ID')->index();
                $table->string('INSCRICAO_STATUS', 20)->default('inscrito'); // inscrito | andamento | concluido
                $table->tinyInteger('INSCRICAO_PROGRESSO')->default(0);      // 0-100
                $table->date('INSCRICAO_DATA_CONCLUSAO')->nullable();
                $table->boolean('INSCRICAO_CERTIFICADO')->default(false);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('TREINAMENTO_INSCRICAO');
        Schema::dropIfExists('TREINAMENTO');
    }
};

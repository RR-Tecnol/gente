<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('AUTOCADASTRO_TOKEN')) {
            Schema::create('AUTOCADASTRO_TOKEN', function (Blueprint $table) {
                $table->increments('TOKEN_ID');
                $table->string('TOKEN', 64)->unique();
                $table->string('TOKEN_EMAIL', 200)->nullable()->comment('E-mail pré-preenchido (opcional)');
                $table->string('TOKEN_NOME', 200)->nullable()->comment('Nome pré-preenchido (opcional)');
                $table->unsignedInteger('FUNCIONARIO_ID')->nullable()->comment('Funcionário criado após preenchimento');
                $table->unsignedInteger('CRIADO_POR')->nullable()->comment('Usuário de RH que gerou o link');
                $table->string('TOKEN_STATUS', 20)->default('pendente')->comment('pendente, preenchido, aprovado, expirado');
                $table->json('TOKEN_DADOS')->nullable()->comment('Dados preenchidos pelo candidato (JSON)');
                $table->timestamp('expira_em')->nullable();
                $table->timestamp('usado_em')->nullable();
                $table->timestamps();

                $table->index('TOKEN_STATUS');
                $table->index('TOKEN');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('AUTOCADASTRO_TOKEN');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('GENTE_ROLE')) {
            return;
        }

        Schema::create('GENTE_ROLE', function (Blueprint $table) {
            $table->increments('GENTE_ROLE_ID');
            $table->string('ROLE_SLUG', 80);
            $table->string('ROLE_NOME', 160);
            $table->string('CAMADA', 32);
            $table->string('ORGAO_TENANT', 16);
            $table->boolean('ROLE_ATIVO')->default(true);
            $table->text('DESCRICAO')->nullable();
            $table->timestamps();

            $table->unique('ROLE_SLUG', 'UQ_GENTE_ROLE_SLUG');
            $table->index(['ORGAO_TENANT', 'CAMADA', 'ROLE_ATIVO'], 'IX_GENTE_ROLE_ORGAO_CAMADA');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('GENTE_ROLE');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('MOTIVO_ALTERACAO_DOMINIO')) {
            return;
        }

        Schema::create('MOTIVO_ALTERACAO_DOMINIO', function (Blueprint $table) {
            $table->increments('MOTIVO_ALTERACAO_ID');
            $table->string('TITULO', 200);
            $table->string('DESCRICAO', 500)->nullable();
            $table->boolean('EXIGE_DOCUMENTO')->default(false);
            $table->boolean('ATIVO')->default(true);
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('MOTIVO_ALTERACAO_DOMINIO')) {
            Schema::drop('MOTIVO_ALTERACAO_DOMINIO');
        }
    }
};

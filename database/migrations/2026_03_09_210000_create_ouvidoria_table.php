<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('OUVIDORIA')) {
            Schema::create('OUVIDORIA', function (Blueprint $table) {
                $table->bigIncrements('OUVIDORIA_ID');
                $table->unsignedBigInteger('FUNCIONARIO_ID')->nullable()->index();
                $table->string('OUVIDORIA_TIPO', 50)->nullable();       // reclamacao, sugestao, elogio...
                $table->string('OUVIDORIA_AREA', 100)->nullable();
                $table->string('OUVIDORIA_URGENCIA', 20)->default('normal');
                $table->text('OUVIDORIA_DESC')->nullable();
                $table->string('OUVIDORIA_STATUS', 30)->default('recebida');
                $table->string('OUVIDORIA_PROTOCOLO', 30)->nullable();
                $table->date('OUVIDORIA_DATA')->nullable();
                $table->tinyInteger('OUVIDORIA_ANONIMO')->default(0);
                $table->text('OUVIDORIA_RESPOSTA')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('OUVIDORIA');
    }
};

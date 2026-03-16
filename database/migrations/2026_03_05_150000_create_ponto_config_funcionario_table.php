<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('PONTO_CONFIG_FUNCIONARIO', function (Blueprint $table) {
            $table->id('PONTO_CONFIG_ID');
            $table->unsignedBigInteger('FUNCIONARIO_ID')->unique();
            // null = usar padrão global
            $table->string('REGIME', 20)->nullable();           // '2_batidas' ou '4_batidas'
            $table->string('HORA_ENTRADA', 5)->nullable();     // 'HH:MM', ex: '08:00'
            $table->string('HORA_SAIDA', 5)->nullable();       // 'HH:MM', ex: '18:00'
            $table->unsignedTinyInteger('TOLERANCIA')->nullable(); // minutos
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('PONTO_CONFIG_FUNCIONARIO');
    }
};

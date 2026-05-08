<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('PROGRESSAO')) {
            return;
        }

        Schema::create('PROGRESSAO', function (Blueprint $table) {
            $table->increments('PROGRESSAO_ID');
            $table->unsignedInteger('FUNCIONARIO_ID');
            $table->string('PROGRESSAO_NIVEL', 20)->nullable();
            $table->string('PROGRESSAO_REFERENCIA', 20)->nullable();
            $table->decimal('PROGRESSAO_SALARIO', 14, 2)->default(0);
            $table->date('PROGRESSAO_DATA')->nullable();
            $table->string('PROGRESSAO_TIPO', 30)->nullable();
            $table->decimal('PROGRESSAO_REAJUSTE', 10, 2)->default(0);
            $table->text('PROGRESSAO_OBS')->nullable();
            $table->boolean('PROGRESSAO_ATIVA')->default(1);
            $table->boolean('PROGRESSAO_FUTURA')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('PROGRESSAO');
    }
};


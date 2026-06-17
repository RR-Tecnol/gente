<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('BENEFICIO')) {
            Schema::create('BENEFICIO', function (Blueprint $table) {
                $table->increments('BENEFICIO_ID');
                $table->string('BENEFICIO_NOME', 150);
                $table->string('BENEFICIO_TIPO', 50)->nullable();
                $table->decimal('BENEFICIO_VALOR', 10, 2)->default(0);
                $table->string('BENEFICIO_STATUS', 20)->default('ativo');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('FUNCIONARIO_BENEFICIO')) {
            Schema::create('FUNCIONARIO_BENEFICIO', function (Blueprint $table) {
                $table->increments('ID');
                $table->unsignedInteger('FUNCIONARIO_ID')->index();
                $table->unsignedInteger('BENEFICIO_ID')->index();
                $table->date('DATA_INICIO')->nullable();
                $table->date('DATA_FIM')->nullable();
                $table->decimal('VALOR_ESPECIFICO', 10, 2)->nullable();
                $table->integer('DEPENDENTES')->default(0);
                $table->string('STATUS', 20)->default('ativo');
                $table->text('OBSERVACAO')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('FUNCIONARIO_BENEFICIO')) {
            Schema::drop('FUNCIONARIO_BENEFICIO');
        }
        if (Schema::hasTable('BENEFICIO')) {
            Schema::drop('BENEFICIO');
        }
    }
};

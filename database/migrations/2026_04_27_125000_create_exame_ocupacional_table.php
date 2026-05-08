<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('EXAME_OCUPACIONAL')) {
            Schema::create('EXAME_OCUPACIONAL', function (Blueprint $table) {
                $table->increments('EXAME_ID');
                $table->unsignedInteger('FUNCIONARIO_ID')->index();
                $table->string('EXAME_TIPO', 50)->default('Periódico');
                $table->string('EXAME_SUBTIPO', 100)->nullable();
                $table->date('EXAME_DATA_REALIZACAO');
                $table->date('EXAME_DATA_VENCIMENTO')->nullable();
                $table->string('EXAME_MEDICO', 150)->nullable();
                $table->boolean('EXAME_APTO')->default(true);
                $table->text('EXAME_OBS')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('EXAME_OCUPACIONAL')) {
            Schema::drop('EXAME_OCUPACIONAL');
        }
    }
};

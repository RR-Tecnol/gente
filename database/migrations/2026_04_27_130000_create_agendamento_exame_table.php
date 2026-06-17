<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('AGENDAMENTO_EXAME')) {
            Schema::create('AGENDAMENTO_EXAME', function (Blueprint $table) {
                $table->increments('AGENDAMENTO_ID');
                $table->unsignedInteger('FUNCIONARIO_ID')->index();
                $table->string('AGENDAMENTO_TIPO', 50);
                $table->date('AGENDAMENTO_DATA')->nullable();
                $table->string('AGENDAMENTO_OBS', 300)->nullable();
                $table->string('AGENDAMENTO_STATUS', 20)->default('pendente');
                $table->date('AGENDAMENTO_DT_SOLICITACAO')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('AGENDAMENTO_EXAME')) {
            Schema::drop('AGENDAMENTO_EXAME');
        }
    }
};

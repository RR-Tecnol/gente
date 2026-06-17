<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('PROGRESSAO_AUTORIZACAO')) {
            return;
        }

        Schema::create('PROGRESSAO_AUTORIZACAO', function (Blueprint $table) {
            $table->increments('PROGRESSAO_AUTORIZACAO_ID');
            $table->unsignedInteger('FUNCIONARIO_ID');
            $table->string('TIPO_OPERACAO', 20)->default('progressao'); // progressao|promocao
            $table->string('ATO_ADMINISTRATIVO', 180);
            $table->text('JUSTIFICATIVA')->nullable();
            $table->string('STATUS', 20)->default('aprovada'); // aprovada|cancelada|expirada
            $table->unsignedInteger('AUTORIZADO_POR')->nullable();
            $table->date('AUTORIZADO_EM')->nullable();
            $table->date('EXPIRA_EM')->nullable();
            $table->timestamp('UTILIZADA_EM')->nullable();
            $table->unsignedInteger('UTILIZADA_POR')->nullable();
            $table->string('USADA_OPERACAO', 20)->nullable();
            $table->decimal('LRF_PERCENTUAL', 7, 2)->nullable();
            $table->timestamps();

            $table->index(['FUNCIONARIO_ID', 'TIPO_OPERACAO'], 'ix_prog_aut_func_tipo');
            $table->index(['STATUS', 'EXPIRA_EM', 'UTILIZADA_EM'], 'ix_prog_aut_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('PROGRESSAO_AUTORIZACAO');
    }
};

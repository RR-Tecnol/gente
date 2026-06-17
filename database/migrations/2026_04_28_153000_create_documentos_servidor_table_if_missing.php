<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('DOCUMENTOS_SERVIDOR')) {
            return;
        }

        Schema::create('DOCUMENTOS_SERVIDOR', function (Blueprint $table) {
            $table->bigIncrements('DOCUMENTO_SERVIDOR_ID');
            $table->unsignedBigInteger('FUNCIONARIO_ID')->index();
            $table->string('TIPO_DOCUMENTO', 80)->index();
            $table->string('TITULO', 255)->nullable();
            $table->string('ARQUIVO_PATH', 500);
            $table->longText('METADADOS_JSON')->nullable();
            $table->string('STATUS_ENVIO_EMAIL', 20)->default('pendente')->index();
            $table->text('ERRO_ENVIO_EMAIL')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('DOCUMENTOS_SERVIDOR')) {
            Schema::drop('DOCUMENTOS_SERVIDOR');
        }
    }
};


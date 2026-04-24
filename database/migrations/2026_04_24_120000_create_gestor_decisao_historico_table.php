<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        try {
            Schema::create('GESTOR_DECISAO_HISTORICO', function (Blueprint $table) {
                $table->bigIncrements('ID');
                $table->integer('GESTOR_USUARIO_ID')->nullable();
                $table->string('REF_TABELA', 80);
                $table->integer('REF_ID');
                $table->string('TIPO', 40)->nullable();
                $table->string('SERVIDOR_NOME', 255)->nullable();
                $table->string('DETALHE', 500)->nullable();
                $table->string('STATUS', 20);
                $table->text('JUSTIFICATIVA')->nullable();
                $table->dateTime('DECIDIDO_EM')->nullable();
                $table->timestamps();
            });
        } catch (\Throwable $e) {
            $msg = strtolower((string) $e->getMessage());
            if (str_contains($msg, 'already an object named') || str_contains($msg, 'already exists')) {
                return;
            }
            throw $e;
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('GESTOR_DECISAO_HISTORICO');
    }
};


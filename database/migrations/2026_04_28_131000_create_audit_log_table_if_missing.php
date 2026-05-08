<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('AUDIT_LOG')) {
            return;
        }

        Schema::create('AUDIT_LOG', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('USUARIO_ID')->nullable();
            $table->string('ACAO', 255)->nullable();
            $table->string('TABELA', 120)->nullable();
            $table->text('DADOS_ANTIGOS')->nullable();
            $table->text('DADOS_NOVOS')->nullable();
            $table->string('IP', 45)->nullable();
            $table->string('USER_AGENT', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('AUDIT_LOG')) {
            Schema::drop('AUDIT_LOG');
        }
    }
};


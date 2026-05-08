<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('CNAB_REMESSA')) {
            Schema::create('CNAB_REMESSA', function (Blueprint $table) {
                $table->increments('CNAB_ID');
                $table->string('CNAB_COMPETENCIA', 7);
                $table->unsignedInteger('BANCO_ID')->nullable();
                $table->string('BANCO_CODIGO', 10)->nullable();
                $table->string('BANCO_NOME', 120)->nullable();
                $table->integer('CNAB_TOTAL_SERVIDORES')->default(0);
                $table->decimal('CNAB_TOTAL_LIQUIDO', 15, 2)->default(0);
                $table->string('CNAB_ARQUIVO', 160)->nullable();
                $table->longText('CNAB_CONTEUDO')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('CNAB_REMESSA')) {
            Schema::drop('CNAB_REMESSA');
        }
    }
};

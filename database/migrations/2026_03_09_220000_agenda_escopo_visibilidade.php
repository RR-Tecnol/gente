<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Cria a tabela AGENDA se ainda não existir
        if (!Schema::hasTable('AGENDA')) {
            Schema::create('AGENDA', function (Blueprint $table) {
                $table->bigIncrements('AGENDA_ID');
                $table->unsignedBigInteger('FUNCIONARIO_ID')->nullable()->index();
                $table->string('AGENDA_TITULO', 200);
                $table->string('AGENDA_TIPO', 50)->nullable();
                $table->date('AGENDA_DATA')->nullable();
                $table->time('AGENDA_HORA')->nullable();
                $table->string('AGENDA_LOCAL', 200)->nullable();
                $table->text('AGENDA_DESC')->nullable();
                // Visibilidade hierárquica
                $table->string('AGENDA_ESCOPO', 20)->default('pessoal'); // pessoal | setor | global
                $table->unsignedBigInteger('AGENDA_SETOR_ID')->nullable()->index(); // NULL = global
                $table->timestamps();
            });
        } else {
            // Adiciona colunas de escopo se a tabela já existir
            Schema::table('AGENDA', function (Blueprint $table) {
                if (!Schema::hasColumn('AGENDA', 'AGENDA_ESCOPO')) {
                    $table->string('AGENDA_ESCOPO', 20)->default('pessoal')->after('AGENDA_DESC');
                }
                if (!Schema::hasColumn('AGENDA', 'AGENDA_SETOR_ID')) {
                    $table->unsignedBigInteger('AGENDA_SETOR_ID')->nullable()->index()->after('AGENDA_ESCOPO');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('AGENDA')) {
            Schema::table('AGENDA', function (Blueprint $table) {
                $table->dropColumnIfExists('AGENDA_ESCOPO');
                $table->dropColumnIfExists('AGENDA_SETOR_ID');
            });
        }
    }
};

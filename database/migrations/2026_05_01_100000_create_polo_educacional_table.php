<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('POLO_EDUCACIONAL')) {
            return;
        }

        Schema::create('POLO_EDUCACIONAL', function (Blueprint $table) {
            $table->increments('POLO_ID');
            $table->string('POLO_NOME', 255);
            $table->string('POLO_SIGLA', 32)->nullable();
            $table->boolean('POLO_ATIVO')->default(true);
            $table->integer('UNIDADE_ID')->nullable()->comment('Unidade administrativa âncora opcional (diretoria regional)');
            $table->text('POLO_OBSERVACAO')->nullable();
            $table->timestamps();

            $table->index(['POLO_ATIVO'], 'IX_POLO_EDUCACIONAL_ATIVO');
            $table->index(['UNIDADE_ID'], 'IX_POLO_EDUCACIONAL_UNIDADE');
        });

        if (Schema::hasTable('UNIDADE')) {
            Schema::table('POLO_EDUCACIONAL', function (Blueprint $table) {
                // sqlsrv: "ON DELETE RESTRICT" gera sintaxe inválida; NO ACTION é equivalente negocial.
                $table->foreign('UNIDADE_ID', 'FK_POLO_EDUCACIONAL_UNIDADE')
                    ->references('UNIDADE_ID')
                    ->on('UNIDADE')
                    ->onDelete('no action');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('POLO_EDUCACIONAL')) {
            return;
        }
        Schema::dropIfExists('POLO_EDUCACIONAL');
    }
};

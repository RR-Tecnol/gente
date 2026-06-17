<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('UNIDADE_POLO')) {
            return;
        }

        Schema::create('UNIDADE_POLO', function (Blueprint $table) {
            $table->increments('UNIDADE_POLO_ID');
            $table->integer('UNIDADE_ID');
            $table->unsignedInteger('POLO_ID');
            $table->date('VIGENCIA_INICIO');
            $table->date('VIGENCIA_FIM')->nullable();
            $table->boolean('VINCULO_ATIVO')->default(true);
            $table->timestamps();

            $table->index(['UNIDADE_ID', 'VINCULO_ATIVO'], 'IX_UNIDADE_POLO_UNIDADE_ATIVO');
            $table->index(['POLO_ID', 'VINCULO_ATIVO'], 'IX_UNIDADE_POLO_POLO_ATIVO');
            $table->index(['POLO_ID', 'VIGENCIA_INICIO', 'VIGENCIA_FIM'], 'IX_UNIDADE_POLO_VIGENCIA');
            $table->unique(['UNIDADE_ID', 'POLO_ID', 'VIGENCIA_INICIO'], 'UQ_UNIDADE_POLO_INICIO');
        });

        if (Schema::hasTable('UNIDADE') && Schema::hasTable('POLO_EDUCACIONAL')) {
            Schema::table('UNIDADE_POLO', function (Blueprint $table) {
                $table->foreign('UNIDADE_ID', 'FK_UNIDADE_POLO_UNIDADE')
                    ->references('UNIDADE_ID')
                    ->on('UNIDADE')
                    ->onDelete('no action');
                $table->foreign('POLO_ID', 'FK_UNIDADE_POLO_POLO')
                    ->references('POLO_ID')
                    ->on('POLO_EDUCACIONAL')
                    ->onDelete('no action');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('UNIDADE_POLO');
    }
};

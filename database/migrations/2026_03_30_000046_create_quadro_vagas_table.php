<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('QUADRO_VAGAS')) {
            Schema::create('QUADRO_VAGAS', function (Blueprint $table) {
                $table->increments('QUADRO_ID');
                $table->unsignedInteger('CARGO_ID');
                $table->unsignedInteger('UNIDADE_ID')->nullable(); // secretaria
                // Total de vagas autorizadas pela lei de criação do cargo
                $table->integer('VAGAS_AUTORIZADAS')->default(0);
                // Lei ou decreto que criou/alterou as vagas
                $table->string('LEI_CRIACAO', 100)->nullable();
                $table->date('DATA_VIGENCIA')->nullable();
                $table->boolean('QUADRO_ATIVO')->default(true);
                $table->unsignedInteger('CRIADO_POR')->nullable();
                $table->timestamps();

                $table->unique(['CARGO_ID', 'UNIDADE_ID'], 'uq_quadro_cargo_unidade');
                $table->index(['CARGO_ID']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('QUADRO_VAGAS');
    }
};

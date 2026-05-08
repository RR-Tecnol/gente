<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('GENTE_PERMISSION')) {
            return;
        }

        Schema::create('GENTE_PERMISSION', function (Blueprint $table) {
            $table->increments('GENTE_PERMISSION_ID');
            $table->string('PERM_SLUG', 120);
            $table->string('PERM_RECURSO', 80);
            $table->string('PERM_ACAO', 80);
            $table->text('PERM_DESCRICAO')->nullable();
            $table->boolean('PERM_ATIVO')->default(true);
            $table->timestamps();

            $table->unique('PERM_SLUG', 'UQ_GENTE_PERMISSION_SLUG');
            $table->index(['PERM_RECURSO', 'PERM_ATIVO'], 'IX_GENTE_PERMISSION_RECURSO');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('GENTE_PERMISSION');
    }
};

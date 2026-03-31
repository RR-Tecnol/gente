<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('DECLARACAO_MODELO')) {
            Schema::create('DECLARACAO_MODELO', function (Blueprint $table) {
                $table->id('MODELO_ID');
                $table->string('MODELO_TIPO', 100)->unique();
                $table->longText('MODELO_HTML');
                $table->timestamp('MODELO_ATUALIZADO_EM')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('DECLARACAO_MODELO');
    }
};

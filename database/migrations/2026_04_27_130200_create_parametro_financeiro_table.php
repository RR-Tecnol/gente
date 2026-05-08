<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('PARAMETRO_FINANCEIRO')) {
            Schema::create('PARAMETRO_FINANCEIRO', function (Blueprint $t) {
                $t->increments('PARAM_ID');
                $t->string('PARAM_TIPO', 30);
                $t->string('PARAM_DESCRICAO', 200);
                $t->string('PARAM_COMPETENCIA', 6)->nullable();
                $t->decimal('PARAM_VALOR', 12, 4);
                $t->string('PARAM_TIPO_VALOR', 20)->default('ALIQUOTA');
                $t->date('PARAM_VIGENCIA_INICIO')->nullable();
                $t->date('PARAM_VIGENCIA_FIM')->nullable();
                $t->timestamps();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('PARAMETRO_FINANCEIRO')) {
            Schema::drop('PARAMETRO_FINANCEIRO');
        }
    }
};

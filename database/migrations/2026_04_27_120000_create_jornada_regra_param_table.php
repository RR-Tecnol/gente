<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('JORNADA_REGRA_PARAM')) {
            return;
        }

        Schema::create('JORNADA_REGRA_PARAM', function (Blueprint $table) {
            $table->bigIncrements('JRP_ID');
            $table->string('JRP_CHAVE', 80);
            $table->decimal('JRP_VALOR_NUM', 12, 6);
            $table->date('JRP_VIGENCIA_INI')->nullable();
            $table->date('JRP_VIGENCIA_FIM')->nullable();
            $table->string('JRP_OBS', 500)->nullable();
            $table->timestamps();

            $table->index(['JRP_CHAVE', 'JRP_VIGENCIA_INI', 'JRP_VIGENCIA_FIM'], 'ix_jornada_regra_vig');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('JORNADA_REGRA_PARAM');
    }
};

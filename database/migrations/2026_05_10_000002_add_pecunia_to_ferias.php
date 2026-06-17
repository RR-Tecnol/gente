<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('FERIAS', function (Blueprint $table) {
            if (!Schema::hasColumn('FERIAS', 'FERIAS_DIAS_PECUNIA')) {
                $table->integer('FERIAS_DIAS_PECUNIA')
                    ->default(0)
                    ->after('FERIAS_AQUISITIVO_FIM')
                    ->comment('Dias convertidos em pecunia (0, 5 ou 10). Max 1/3 de 30 dias.');
            }
            if (!Schema::hasColumn('FERIAS', 'FERIAS_FOLHA_APLICADA')) {
                $table->string('FERIAS_FOLHA_APLICADA', 6)
                    ->nullable()
                    ->after('FERIAS_DIAS_PECUNIA')
                    ->comment('Competencia YYYYMM em que o 1/3 foi aplicado na folha. Controle de idempotencia.');
            }
        });
    }

    public function down(): void
    {
        Schema::table('FERIAS', function (Blueprint $table) {
            if (Schema::hasColumn('FERIAS', 'FERIAS_DIAS_PECUNIA')) {
                $table->dropColumn('FERIAS_DIAS_PECUNIA');
            }
            if (Schema::hasColumn('FERIAS', 'FERIAS_FOLHA_APLICADA')) {
                $table->dropColumn('FERIAS_FOLHA_APLICADA');
            }
        });
    }
};

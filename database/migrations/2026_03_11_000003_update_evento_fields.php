<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Expande a tabela EVENTO com campos para:
 * - Código de-para SAGRES (integração TCE-MA)
 * - Categoria do evento (hora extra, plantão, indenizatório, etc.)
 * - Flags de incidência tributária/previdenciária
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('EVENTO', function (Blueprint $table) {
            if (!Schema::hasColumn('EVENTO', 'EVENTO_CODIGO')) {
                $table->string('EVENTO_CODIGO', 10)->nullable()->after('EVENTO_ID');
            }
            if (!Schema::hasColumn('EVENTO', 'EVENTO_CATEGORIA')) {
                // BASE | ADICIONAL | HORA_EXTRA | PLANTAO | INDENIZATORIO | PREVIDENCIARIO | FISCAL
                $table->string('EVENTO_CATEGORIA', 30)->nullable()->after('EVENTO_NOME');
            }
            if (!Schema::hasColumn('EVENTO', 'EVENTO_INCIDE_INSS')) {
                $table->boolean('EVENTO_INCIDE_INSS')->default(true)->after('EVENTO_TIPO');
            }
            if (!Schema::hasColumn('EVENTO', 'EVENTO_INCIDE_IRRF')) {
                $table->boolean('EVENTO_INCIDE_IRRF')->default(true)->after('EVENTO_INCIDE_INSS');
            }
            if (!Schema::hasColumn('EVENTO', 'EVENTO_INCIDE_RPPS')) {
                $table->boolean('EVENTO_INCIDE_RPPS')->default(true)->after('EVENTO_INCIDE_IRRF');
            }
            if (!Schema::hasColumn('EVENTO', 'EVENTO_ATIVO')) {
                $table->boolean('EVENTO_ATIVO')->default(true)->after('EVENTO_INCIDE_RPPS');
            }
        });
    }

    public function down(): void
    {
        Schema::table('EVENTO', function (Blueprint $table) {
            foreach ([
                'EVENTO_CODIGO',
                'EVENTO_CATEGORIA',
                'EVENTO_INCIDE_INSS',
                'EVENTO_INCIDE_IRRF',
                'EVENTO_INCIDE_RPPS',
                'EVENTO_ATIVO',
            ] as $col) {
                if (Schema::hasColumn('EVENTO', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};

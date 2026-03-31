<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('FERIAS', function (Blueprint $table) {
            // Controle de aprovação
            if (!Schema::hasColumn('FERIAS', 'FERIAS_STATUS')) {
                // AGENDADA | APROVADA | PAGA | CANCELADA
                $table->string('FERIAS_STATUS', 20)->default('AGENDADA')->after('FERIAS_ID');
            }
            if (!Schema::hasColumn('FERIAS', 'FERIAS_DIAS')) {
                $table->integer('FERIAS_DIAS')->default(30)->after('FERIAS_DATA_FIM');
            }
            if (!Schema::hasColumn('FERIAS', 'APROVADO_POR')) {
                $table->unsignedInteger('APROVADO_POR')->nullable()->after('FERIAS_DIAS');
            }
            if (!Schema::hasColumn('FERIAS', 'APROVADO_EM')) {
                $table->dateTime('APROVADO_EM')->nullable()->after('APROVADO_POR');
            }
            // Valores financeiros calculados no momento da aprovação
            if (!Schema::hasColumn('FERIAS', 'FERIAS_VALOR_BASE')) {
                $table->decimal('FERIAS_VALOR_BASE', 15, 2)->default(0)->after('APROVADO_EM');
            }
            if (!Schema::hasColumn('FERIAS', 'FERIAS_VALOR_TERCO')) {
                // 1/3 constitucional (art. 7º, XVII CF/88)
                $table->decimal('FERIAS_VALOR_TERCO', 15, 2)->default(0)->after('FERIAS_VALOR_BASE');
            }
            if (!Schema::hasColumn('FERIAS', 'FERIAS_VALOR_INSS')) {
                $table->decimal('FERIAS_VALOR_INSS', 15, 2)->default(0)->after('FERIAS_VALOR_TERCO');
            }
            if (!Schema::hasColumn('FERIAS', 'FERIAS_VALOR_IRRF')) {
                $table->decimal('FERIAS_VALOR_IRRF', 15, 2)->default(0)->after('FERIAS_VALOR_INSS');
            }
            if (!Schema::hasColumn('FERIAS', 'FERIAS_VALOR_LIQUIDO')) {
                $table->decimal('FERIAS_VALOR_LIQUIDO', 15, 2)->default(0)->after('FERIAS_VALOR_IRRF');
            }
            if (!Schema::hasColumn('FERIAS', 'FERIAS_COMPETENCIA_PAGAMENTO')) {
                // Competência em que o pagamento entra na folha (AAAAMM)
                $table->string('FERIAS_COMPETENCIA_PAGAMENTO', 6)->nullable()->after('FERIAS_VALOR_LIQUIDO');
            }
            if (!Schema::hasColumn('FERIAS', 'DETALHE_FOLHA_ID')) {
                // Vínculo com o lançamento gerado na folha
                $table->unsignedInteger('DETALHE_FOLHA_ID')->nullable()->after('FERIAS_COMPETENCIA_PAGAMENTO');
            }
        });
    }

    public function down(): void
    {
        Schema::table('FERIAS', function (Blueprint $table) {
            $table->dropColumn([
                'FERIAS_STATUS', 'FERIAS_DIAS', 'APROVADO_POR', 'APROVADO_EM',
                'FERIAS_VALOR_BASE', 'FERIAS_VALOR_TERCO', 'FERIAS_VALOR_INSS',
                'FERIAS_VALOR_IRRF', 'FERIAS_VALOR_LIQUIDO',
                'FERIAS_COMPETENCIA_PAGAMENTO', 'DETALHE_FOLHA_ID',
            ]);
        });
    }
};

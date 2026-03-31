<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // ── RUBRICA ──────────────────────────────────────────────────────────
        if (!Schema::hasTable('RUBRICA')) {
            Schema::create('RUBRICA', function (Blueprint $table) {
                $table->integer('RUBRICA_ID')->autoIncrement();
                $table->string('RUBRICA_CODIGO', 10);
                $table->string('RUBRICA_DESCRICAO', 200);
                $table->char('RUBRICA_TIPO', 1)->default('P'); // P=Provento, D=Desconto
                $table->integer('RUBRICA_ATIVO')->default(1);
            });

            DB::table('RUBRICA')->insert([
                ['RUBRICA_CODIGO' => '001', 'RUBRICA_DESCRICAO' => 'Vencimento Base', 'RUBRICA_TIPO' => 'P'],
                ['RUBRICA_CODIGO' => '010', 'RUBRICA_DESCRICAO' => 'Gratificação de Função', 'RUBRICA_TIPO' => 'P'],
                ['RUBRICA_CODIGO' => '020', 'RUBRICA_DESCRICAO' => 'Adicional de Tempo de Serviço', 'RUBRICA_TIPO' => 'P'],
                ['RUBRICA_CODIGO' => '900', 'RUBRICA_DESCRICAO' => 'INSS / IPAM', 'RUBRICA_TIPO' => 'D'],
                ['RUBRICA_CODIGO' => '901', 'RUBRICA_DESCRICAO' => 'IRRF', 'RUBRICA_TIPO' => 'D'],
                ['RUBRICA_CODIGO' => '902', 'RUBRICA_DESCRICAO' => 'Contribuição Sindical', 'RUBRICA_TIPO' => 'D'],
            ]);
        }

        // ── ITEM_FOLHA ────────────────────────────────────────────────────────
        if (!Schema::hasTable('ITEM_FOLHA')) {
            Schema::create('ITEM_FOLHA', function (Blueprint $table) {
                $table->integer('ITEM_FOLHA_ID')->autoIncrement();
                $table->integer('DETALHE_FOLHA_ID');
                $table->integer('RUBRICA_ID');
                $table->char('ITEM_TIPO', 1)->default('P'); // P=Provento, D=Desconto
                $table->decimal('ITEM_VALOR', 12, 2)->default(0);
                $table->string('ITEM_REFERENCIA', 50)->nullable();
            });

            // Seed: rubricas individuais do holerite 1 (FUNCIONARIO_ID=1 / ADM)
            $detalhes = DB::table('DETALHE_FOLHA')->get();
            foreach ($detalhes as $df) {
                $prov = (float) ($df->DETALHE_FOLHA_PROVENTOS ?? 0);
                $desc = (float) ($df->DETALHE_FOLHA_DESCONTOS ?? 0);

                if ($prov > 0) {
                    // Vencimento Base = 70% dos proventos
                    DB::table('ITEM_FOLHA')->insert([
                        'DETALHE_FOLHA_ID' => $df->DETALHE_FOLHA_ID,
                        'RUBRICA_ID' => 1, // 001 - Vencimento Base
                        'ITEM_TIPO' => 'P',
                        'ITEM_VALOR' => round($prov * 0.70, 2),
                        'ITEM_REFERENCIA' => null,
                    ]);
                    // Gratificação de Função = 30% dos proventos
                    DB::table('ITEM_FOLHA')->insert([
                        'DETALHE_FOLHA_ID' => $df->DETALHE_FOLHA_ID,
                        'RUBRICA_ID' => 2, // 010 - Gratificação
                        'ITEM_TIPO' => 'P',
                        'ITEM_VALOR' => round($prov * 0.30, 2),
                        'ITEM_REFERENCIA' => null,
                    ]);
                }

                if ($desc > 0) {
                    // INSS/IPAM = 60% dos descontos
                    DB::table('ITEM_FOLHA')->insert([
                        'DETALHE_FOLHA_ID' => $df->DETALHE_FOLHA_ID,
                        'RUBRICA_ID' => 4, // 900 - INSS/IPAM
                        'ITEM_TIPO' => 'D',
                        'ITEM_VALOR' => round($desc * 0.60, 2),
                        'ITEM_REFERENCIA' => null,
                    ]);
                    // IRRF = 40% dos descontos
                    DB::table('ITEM_FOLHA')->insert([
                        'DETALHE_FOLHA_ID' => $df->DETALHE_FOLHA_ID,
                        'RUBRICA_ID' => 5, // 901 - IRRF
                        'ITEM_TIPO' => 'D',
                        'ITEM_VALOR' => round($desc * 0.40, 2),
                        'ITEM_REFERENCIA' => null,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ITEM_FOLHA');
        Schema::dropIfExists('RUBRICA');
    }
};

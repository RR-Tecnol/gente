<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cobertura fiscal mínima e auditável para homolog (Domínio 4).
 *
 * Extrahido de {@see SystemPhase2CoverageSeeder} para isolamento e documentação.
 * Respeita os invariantes em `docs/davi/FISCAL_SEED_INVARIANTS.md`.
 * Deve correr depois de {@see SidebarCoverageSeeder} (PPA/LOA mínimos) e {@see PcaspSeeder}.
 */
class ErpFiscalCoverageSeeder extends Seeder
{
    public function run(): void
    {
        $adminId = (int) (DB::table('USUARIO')->where('USUARIO_LOGIN', 'admin')->value('USUARIO_ID') ?? 1);
        if ($adminId <= 0) {
            $adminId = 1;
        }

        if (Schema::hasTable('ORCAMENTO_LOA') && Schema::hasTable('EMPENHO') && Schema::hasTable('LIQUIDACAO') && Schema::hasTable('PAGAMENTO_DESPESA')) {
            $loaId = DB::table('ORCAMENTO_LOA')->value('LOA_ID');
            if ($loaId) {
                $empenhoId = DB::table('EMPENHO')->where('EMPENHO_NUMERO', '2026NE000123')->value('EMPENHO_ID');
                if (! $empenhoId) {
                    $empenhoId = DB::table('EMPENHO')->insertGetId([
                        'LOA_ID' => $loaId,
                        'EMPENHO_NUMERO' => '2026NE000123',
                        'EMPENHO_DATA' => now()->subDays(12)->toDateString(),
                        'EMPENHO_CREDOR' => 'Fornecedor Hospitalar Alpha',
                        'EMPENHO_CPFCNPJ' => '12.345.678/0001-90',
                        'EMPENHO_HISTORICO' => 'Aquisição de insumos para pronto atendimento',
                        'EMPENHO_VALOR' => 182000,
                        'EMPENHO_TIPO' => 'ORDINARIO',
                        'EMPENHO_STATUS' => 'LIQUIDADO',
                        'USUARIO_ID' => $adminId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
                $liqId = DB::table('LIQUIDACAO')->where('EMPENHO_ID', $empenhoId)->value('LIQUIDACAO_ID');
                if (! $liqId) {
                    $liqId = DB::table('LIQUIDACAO')->insertGetId([
                        'EMPENHO_ID' => $empenhoId,
                        'LIQUIDACAO_DATA' => now()->subDays(7)->toDateString(),
                        'LIQUIDACAO_VALOR' => 161000,
                        'LIQUIDACAO_HISTORICO' => 'Entrega parcial confirmada',
                        'LIQUIDACAO_NF' => 'NF-2026-8891',
                        'USUARIO_ID' => $adminId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
                DB::table('PAGAMENTO_DESPESA')->updateOrInsert(
                    ['LIQUIDACAO_ID' => $liqId, 'PAGAMENTO_DATA' => now()->subDays(2)->toDateString()],
                    [
                        'PAGAMENTO_VALOR' => 161000,
                        'PAGAMENTO_FORMA' => 'TRANSFERENCIA',
                        'PAGAMENTO_BANCO' => '001',
                        'PAGAMENTO_CONTA' => '12345-7',
                        'PAGAMENTO_HISTORICO' => 'Pagamento de liquidação seed',
                        'USUARIO_ID' => $adminId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }

        if (Schema::hasTable('PCASP_CONTA') && Schema::hasTable('LANCAMENTO_CONTABIL')) {
            $contaDebito = DB::table('PCASP_CONTA')->where('CONTA_CODIGO', '3.3.90.30.00')->value('CONTA_ID');
            if (! $contaDebito) {
                $contaDebito = DB::table('PCASP_CONTA')->insertGetId([
                    'CONTA_PAI_ID' => null,
                    'CONTA_CODIGO' => '3.3.90.30.00',
                    'CONTA_NOME' => 'Material de Consumo',
                    'CONTA_NATUREZA' => 'DEVEDORA',
                    'CONTA_TIPO' => 'ANALITICA',
                    'CONTA_GRUPO' => 'VARIACAO_PATRIMONIAL_DIMINUTIVA',
                    'CONTA_ATIVA' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            $contaCredito = DB::table('PCASP_CONTA')->where('CONTA_CODIGO', '1.1.1.1.1.01')->value('CONTA_ID');
            if (! $contaCredito) {
                $contaCredito = DB::table('PCASP_CONTA')->insertGetId([
                    'CONTA_PAI_ID' => null,
                    'CONTA_CODIGO' => '1.1.1.1.1.01',
                    'CONTA_NOME' => 'Caixa e Equivalentes',
                    'CONTA_NATUREZA' => 'CREDORA',
                    'CONTA_TIPO' => 'ANALITICA',
                    'CONTA_GRUPO' => 'ATIVO',
                    'CONTA_ATIVA' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            DB::table('LANCAMENTO_CONTABIL')->updateOrInsert(
                ['LANCAMENTO_DATA' => now()->subDays(2)->toDateString(), 'LANCAMENTO_HISTORICO' => 'Lançamento seed material hospitalar'],
                [
                    'LANCAMENTO_ANO' => (int) now()->format('Y'),
                    'LANCAMENTO_MES' => (int) now()->format('m'),
                    'LANCAMENTO_VALOR' => 161000,
                    'CONTA_DEBITO_ID' => $contaDebito,
                    'CONTA_CREDITO_ID' => $contaCredito,
                    'ORIGEM_TIPO' => 'PAGAMENTO',
                    'ORIGEM_ID' => null,
                    'USUARIO_ID' => $adminId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        if (Schema::hasTable('RECEITA_DIVIDA_ATIVA')) {
            DB::table('RECEITA_DIVIDA_ATIVA')->updateOrInsert(
                ['DA_INSCRICAO' => 'DA-2026-0001'],
                [
                    'DA_DEVEDOR' => 'Clínica Conveniada Delta',
                    'DA_CPFCNPJ' => '98.765.432/0001-10',
                    'DA_DATA_INSCRICAO' => now()->subMonths(3)->toDateString(),
                    'DA_VALOR_PRINCIPAL' => 45000,
                    'DA_MULTA' => 2300,
                    'DA_JUROS' => 980,
                    'DA_HONORARIO' => 1200,
                    'DA_STATUS' => 'PARCELADA',
                    'DA_HISTORICO' => 'Parcelamento em 6x validado pela procuradoria.',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}

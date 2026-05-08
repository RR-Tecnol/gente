<?php

namespace Database\Seeders;

use App\Services\Progressao\ProgressaoFuncionalElegibilidadeService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 10B — cobertura mínima para Saúde e Benefícios (SEMED): LM &gt; 15 dias (CID Z73),
 * consignação dentro de 30% do vencimento-base simulado, agendamentos SESMT.
 *
 * Opt-in: {@see env('GENTE_SAUDE_BENEFICIOS_SEED')} = true/1 (evita seed pesado em CI).
 */
class SaudeEBeneficiosCoverageSeeder extends Seeder
{
    public function run(): void
    {
        if (! filter_var(env('GENTE_SAUDE_BENEFICIOS_SEED', false), FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        if (! Schema::hasTable('FUNCIONARIO')) {
            return;
        }

        if ($this->alreadySeeded10B()) {
            $this->command?->info('SAUDE-BENEFICIOS-10B: já materializado (marcador 10B_SEED_COVERAGE).');

            return;
        }

        $funcionarios = $this->resolverFuncionariosSemed();
        if ($funcionarios->isEmpty()) {
            $this->command?->warn('SAUDE-BENEFICIOS-10B: nenhum funcionário SEMED com lotação ativa — skip.');

            return;
        }

        $eleg = app(ProgressaoFuncionalElegibilidadeService::class);
        $adminId = (int) (DB::table('USUARIO')->where('USUARIO_ATIVO', 1)->value('USUARIO_ID') ?? 1);

        $principal = $funcionarios->first();
        $this->seedAfastamentoLmLonga($principal);
        $this->seedAtestadoZ73($principal, $adminId);

        foreach ($funcionarios->take(4) as $f) {
            $this->seedConsignacaoLimitada30($f, $eleg, $adminId);
        }

        $this->seedAgendamentosExames($funcionarios->take(6));

        $this->command?->info('SAUDE-BENEFICIOS-10B: concluído (AFASTAMENTO / consignação / exames).');
    }

    private function alreadySeeded10B(): bool
    {
        if (Schema::hasTable('CONSIG_CONTRATO') && Schema::hasColumn('CONSIG_CONTRATO', 'OBSERVACAO')) {
            if (DB::table('CONSIG_CONTRATO')->where('OBSERVACAO', 'like', '%10B_SEED_COVERAGE%')->exists()) {
                return true;
            }
        }
        if (Schema::hasTable('ATESTADO_MEDICO') && Schema::hasColumn('ATESTADO_MEDICO', 'OBSERVACAO')) {
            return DB::table('ATESTADO_MEDICO')->where('OBSERVACAO', 'like', '%10B_SEED_COVERAGE%')->exists();
        }

        return false;
    }

    private function resolverFuncionariosSemed(): \Illuminate\Support\Collection
    {
        if (! Schema::hasTable('LOTACAO') || ! Schema::hasTable('SETOR') || ! Schema::hasTable('UNIDADE')) {
            return collect();
        }

        return DB::table('FUNCIONARIO as f')
            ->join('LOTACAO as l', function ($j) {
                $j->on('l.FUNCIONARIO_ID', '=', 'f.FUNCIONARIO_ID')->whereNull('l.LOTACAO_DATA_FIM');
            })
            ->join('SETOR as s', 's.SETOR_ID', '=', 'l.SETOR_ID')
            ->join('UNIDADE as u', 'u.UNIDADE_ID', '=', 's.UNIDADE_ID')
            ->where('u.UNIDADE_SIGLA', 'SEMED')
            ->whereNull('f.FUNCIONARIO_DATA_FIM')
            ->orderBy('f.FUNCIONARIO_ID')
            ->select('f.*')
            ->limit(16)
            ->get();
    }

    private function seedAfastamentoLmLonga(object $func): void
    {
        if (! Schema::hasTable('AFASTAMENTO')) {
            return;
        }

        $ini = Carbon::now()->subDays(22)->toDateString();
        $fim = Carbon::now()->subDays(1)->toDateString();
        $payload = [
            'FUNCIONARIO_ID' => (int) $func->FUNCIONARIO_ID,
            'AFASTAMENTO_DATA_INICIO' => $ini,
            'AFASTAMENTO_DATA_FIM' => $fim,
            'AFASTAMENTO_TIPO' => 8,
        ];
        if (Schema::hasColumn('AFASTAMENTO', 'AFASTAMENTO_STATUS')) {
            $payload['AFASTAMENTO_STATUS'] = 'DEFERIDO';
        }
        if (Schema::hasColumn('AFASTAMENTO', 'AFASTAMENTO_MOTIVO')) {
            $payload['AFASTAMENTO_MOTIVO'] = 'Seed 10B — LM eSocial 01/03 (narrativa); CID Z73 cobertura escala &gt;15d.';
        }

        DB::table('AFASTAMENTO')->insert($payload);
    }

    private function seedAtestadoZ73(object $func, int $adminId): void
    {
        if (! Schema::hasTable('ATESTADO_MEDICO')) {
            return;
        }

        $cols = Schema::getColumnListing('ATESTADO_MEDICO');
        $row = [
            'FUNCIONARIO_ID' => (int) $func->FUNCIONARIO_ID,
            'ATESTADO_DATA' => Carbon::now()->subDays(30)->toDateString(),
            'ATESTADO_DIAS' => 22,
            'ATESTADO_CID' => 'Z73',
            'MEDICO_NOME' => 'Dr. Seed Cobertura 10B',
            'MEDICO_CRM' => 'CRM/MA 000000',
            'STATUS' => 'VALIDADO',
            'OBSERVACAO' => '10B_SEED_COVERAGE atestado LM &gt;15d (Z73).',
            'created_at' => now(),
            'updated_at' => now(),
        ];
        if (in_array('VALIDADO_POR', $cols, true)) {
            $row['VALIDADO_POR'] = $adminId;
        }
        $row = array_intersect_key($row, array_flip($cols));
        DB::table('ATESTADO_MEDICO')->insert($row);
    }

    private function seedConsignacaoLimitada30(object $func, ProgressaoFuncionalElegibilidadeService $eleg, int $adminId): void
    {
        if (! Schema::hasTable('CONSIG_CONTRATO') || ! Schema::hasTable('CONSIG_CONVENIO')) {
            return;
        }

        $convenioId = (int) (DB::table('CONSIG_CONVENIO')->where('ATIVO', 1)->orderBy('CONVENIO_ID')->value('CONVENIO_ID') ?? 1);
        $venc = max(1500.0, $eleg->getVencBase($func));
        $teto30 = round($venc * 0.30, 2);
        $parcela = min(round($teto30 * 0.75, 2), $teto30);
        $prazo = 48;
        $total = round($parcela * $prazo, 2);

        $cols = Schema::getColumnListing('CONSIG_CONTRATO');
        $payload = [
            'FUNCIONARIO_ID' => (int) $func->FUNCIONARIO_ID,
            'CONVENIO_ID' => $convenioId,
            'NUMERO_CONTRATO' => '10B-COV-SEED-'.str_pad((string) $func->FUNCIONARIO_ID, 6, '0', STR_PAD_LEFT),
            'DATA_INICIO' => now()->toDateString(),
            'DATA_FIM' => null,
            'VALOR_TOTAL' => $total,
            'VALOR_PARCELA' => $parcela,
            'PRAZO_MESES' => $prazo,
            'PARCELAS_PAGAS' => 0,
            'SALDO_DEVEDOR' => $total,
            'TAXA_JUROS' => 1.2,
            'STATUS' => 'ATIVO',
            'OBSERVACAO' => '10B_SEED_COVERAGE consignação; parcela <= 30% vencimento base simulado.',
            'CADASTRADO_POR' => $adminId,
            'created_at' => now(),
            'updated_at' => now(),
        ];
        $payload = array_intersect_key($payload, array_flip($cols));
        DB::table('CONSIG_CONTRATO')->insert($payload);
    }

    private function seedAgendamentosExames(\Illuminate\Support\Collection $funcionarios): void
    {
        if (! Schema::hasTable('AGENDAMENTO_EXAME')) {
            return;
        }

        $i = 0;
        foreach ($funcionarios as $f) {
            DB::table('AGENDAMENTO_EXAME')->insert([
                'FUNCIONARIO_ID' => (int) $f->FUNCIONARIO_ID,
                'AGENDAMENTO_TIPO' => 'periódico',
                'AGENDAMENTO_DATA' => now()->addDays(14 + $i * 3)->toDateString(),
                'AGENDAMENTO_OBS' => '10B SESMT — exame periódico (seed cobertura).',
                'AGENDAMENTO_STATUS' => 'pendente',
                'AGENDAMENTO_DT_SOLICITACAO' => now()->toDateString(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $i++;
        }
    }
}

<?php

namespace App\Console\Commands;

use App\Support\GenteAuditWriter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class GenteEscalaExpurgoOrfaosCommand extends Command
{
    protected $signature = 'gente:escala-expurgar-orfaos {--apply : Executa expurgo real (sem --apply é simulação)}';

    protected $description = 'Expurga itens órfãos/inconsistentes da escala (inativos, sem lotação ativa, resquícios de laboratório)';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        if (!$apply) {
            $this->warn('Modo simulação (dry-run). Use --apply para expurgar.');
        }

        $hoje = now()->toDateString();
        $temLotacaoFim = Schema::hasColumn('LOTACAO', 'LOTACAO_DATA_FIM');
        $temFuncionarioFim = Schema::hasColumn('FUNCIONARIO', 'FUNCIONARIO_DATA_FIM');

        $base = DB::table('DETALHE_ESCALA as de')
            ->join('FUNCIONARIO as f', 'f.FUNCIONARIO_ID', '=', 'de.FUNCIONARIO_ID')
            ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
            ->leftJoin('LOTACAO as l', function ($join) use ($temLotacaoFim) {
                $join->on('l.FUNCIONARIO_ID', '=', 'f.FUNCIONARIO_ID');
                if ($temLotacaoFim) {
                    $join->whereNull('l.LOTACAO_DATA_FIM');
                }
            })
            ->select(
                'de.DETALHE_ESCALA_ID',
                'de.FUNCIONARIO_ID',
                'p.PESSOA_NOME',
                DB::raw('MAX(l.LOTACAO_ID) as LOTACAO_ATIVA_ID'),
                DB::raw('MAX(f.FUNCIONARIO_DATA_FIM) as FUNCIONARIO_DATA_FIM')
            )
            ->groupBy('de.DETALHE_ESCALA_ID', 'de.FUNCIONARIO_ID', 'p.PESSOA_NOME');

        $candidatos = $base->get()->filter(function ($r) use ($temFuncionarioFim, $hoje) {
            $nome = mb_strtoupper(trim((string) ($r->PESSOA_NOME ?? '')), 'UTF-8');
            $isLab = str_contains($nome, 'LAB');
            $semLotacao = empty($r->LOTACAO_ATIVA_ID);
            $inativo = false;
            if ($temFuncionarioFim) {
                $fim = $r->FUNCIONARIO_DATA_FIM;
                $inativo = !empty($fim) && $fim <= $hoje;
            }
            return $isLab || $semLotacao || $inativo;
        })->values();

        $detalheIds = $candidatos->pluck('DETALHE_ESCALA_ID')->map(fn ($v) => (int) $v)->unique()->values();
        $funcIds = $candidatos->pluck('FUNCIONARIO_ID')->map(fn ($v) => (int) $v)->unique()->values();

        $itensCount = 0;
        if ($detalheIds->isNotEmpty()) {
            $itensCount = DB::table('DETALHE_ESCALA_ITEM')->whereIn('DETALHE_ESCALA_ID', $detalheIds)->count();
        }

        $this->line('--- Relatório de Expurgo ---');
        $this->line('Funcionários inconsistentes: ' . $funcIds->count());
        $this->line('Detalhes de escala inconsistentes: ' . $detalheIds->count());
        $this->line('Itens de escala candidatos ao expurgo: ' . $itensCount);

        if ($apply && $detalheIds->isNotEmpty()) {
            DB::transaction(function () use ($detalheIds, $funcIds, $itensCount) {
                DB::table('DETALHE_ESCALA_ITEM')->whereIn('DETALHE_ESCALA_ID', $detalheIds)->delete();
                DB::table('DETALHE_ESCALA')->whereIn('DETALHE_ESCALA_ID', $detalheIds)->delete();

                if (Schema::hasTable('AUDIT_LOG')) {
                    $cols = Schema::getColumnListing('AUDIT_LOG');
                    $ctx = json_encode([
                        'titulo' => 'Registros Expurgados por Inconsistência',
                        'funcionarios_afetados' => $funcIds->all(),
                        'detalhes_expurgados' => $detalheIds->all(),
                        'itens_expurgados' => $itensCount,
                    ], JSON_UNESCAPED_UNICODE);
                    $payload = [];
                    if (in_array('acao', $cols, true)) $payload['acao'] = 'EXPURGO_ESCALA_INCONSISTENTE';
                    if (in_array('evento', $cols, true)) $payload['evento'] = 'ESCALA_HIGIENIZACAO';
                    if (in_array('event_type', $cols, true)) $payload['event_type'] = 'DATA_CLEANUP';
                    if (in_array('priority', $cols, true)) $payload['priority'] = 'high';
                    if (in_array('contexto', $cols, true)) $payload['contexto'] = $ctx;
                    if (in_array('context', $cols, true)) $payload['context'] = $ctx;
                    if (in_array('dados', $cols, true)) $payload['dados'] = $ctx;
                    if (in_array('usuario_id', $cols, true)) $payload['usuario_id'] = auth()->id();
                    if (in_array('created_at', $cols, true)) $payload['created_at'] = now();
                    if (in_array('updated_at', $cols, true)) $payload['updated_at'] = now();
                    if (in_array('DATA_HORA', $cols, true)) $payload['DATA_HORA'] = now();
                    if (!empty($payload)) {
                        GenteAuditWriter::insertChainedRow($payload);
                    }
                }
            });
            $this->info('Expurgo concluído com sucesso.');
        }

        if (!$apply) {
            $this->line('Nenhum registro foi removido (dry-run).');
        }

        return self::SUCCESS;
    }
}


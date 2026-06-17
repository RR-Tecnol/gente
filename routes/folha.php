<?php

use App\Models\DetalheFolha;
use App\Models\Folha;
use App\Services\MotorFolhaService;
use App\Support\FolhaCompetenciaCabecalho;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

// ⚠️ NÃO abrir Route::middleware()->prefix()->group() aqui
// O contexto api/v3 + auth já é herdado do web.php — ver regra §2 de regras-gerais.md

// GET /api/v3/folhas — Lista folhas de pagamento com totais
Route::get('/folhas', function () {
    try {
        $rows = DB::table('FOLHA')
            ->orderBy('FOLHA_COMPETENCIA', 'desc')
            ->limit(50)
            ->get();

        // Mapeia FOLHA_STATUS ('Fechada'/'Aberta') → FOLHA_SITUACAO ('F'/'A')
        $statusMap = [
            'Fechada' => 'F',
            'fechada' => 'F',
            'Aberta' => 'A',
            'aberta' => 'A',
            'Em Processamento' => 'P',
            'Cancelada' => 'C',
            'F' => 'F',
            'A' => 'A',
            'P' => 'P',
            'C' => 'C',
        ];

        // Converte competência 'YYYY-MM' → 'MMYYYY' (formato que o frontend formata)
        $compConvert = function ($c) {
            if (!$c)
                return null;
            if (preg_match('/^(\d{4})-(\d{2})$/', $c, $m))
                return $m[2] . $m[1];
            if (preg_match('/^(20\d{2})(\d{2})$/', $c, $m))
                return $m[2] . $m[1];
            if (preg_match('/^(\d{2})(20\d{2})$/', $c))
                return $c;
            $meses = [
                'Jan' => '01',
                'Fev' => '02',
                'Mar' => '03',
                'Abr' => '04',
                'Mai' => '05',
                'Jun' => '06',
                'Jul' => '07',
                'Ago' => '08',
                'Set' => '09',
                'Out' => '10',
                'Nov' => '11',
                'Dez' => '12'
            ];
            if (preg_match('/^([A-Za-z]{3})\/(\d{4})$/', $c, $m)) {
                return ($meses[$m[1]] ?? '01') . $m[2];
            }
            return $c;
        };

        $folhas = $rows->map(function ($f) use ($statusMap, $compConvert) {
            $totais = DB::table('DETALHE_FOLHA')
                ->where('FOLHA_ID', $f->FOLHA_ID)
                ->whereNull('DETALHE_FOLHA_ERRO')
                ->selectRaw('COUNT(*) as qtd, SUM(DETALHE_FOLHA_PROVENTOS) as prov, SUM(DETALHE_FOLHA_DESCONTOS) as desc_val')
                ->first();

            $statusRaw = $f->FOLHA_STATUS ?? $f->FOLHA_SITUACAO ?? 'A';
            $situacao = $statusMap[$statusRaw] ?? 'A';

            return [
                'FOLHA_ID' => $f->FOLHA_ID,
                'FOLHA_COMPETENCIA' => $compConvert($f->FOLHA_COMPETENCIA),
                'FOLHA_COMPETENCIA_RAW' => $f->FOLHA_COMPETENCIA,
                'FOLHA_SITUACAO' => $situacao,
                'qtd_funcionarios' => (int) ($totais->qtd ?? $f->FOLHA_QTD_SERVIDORES ?? 0),
                'total_proventos' => (float) ($totais->prov ?? $f->FOLHA_VALOR_TOTAL ?? 0),
                'total_descontos' => (float) ($totais->desc_val ?? 0),
                'total_liquido' => (float) (($totais->prov ?? 0) - ($totais->desc_val ?? 0)),
                'FOLHA_DESCRICAO' => $f->FOLHA_DESCRICAO ?? null,
            ];
        });

        return response()->json($folhas);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// GET /api/v3/folhas/consistencia/{competencia} — Cross-check Folha x Consignacao x RPPS
Route::get('/folhas/consistencia/{competencia}', function (string $competencia) {
    try {
        $comp = preg_replace('/[^0-9]/', '', $competencia);
        if (strlen($comp) === 6 && substr($comp, 0, 2) >= '01' && substr($comp, 0, 2) <= '12') {
            $comp = substr($comp, 2) . substr($comp, 0, 2); // MMYYYY -> YYYYMM
        }
        $compYm = substr($comp, 0, 4) . '-' . substr($comp, 4, 2);

        $folha = DB::table('FOLHA')
            ->whereIn('FOLHA_COMPETENCIA', [$comp, $compYm, $competencia])
            ->orderByDesc('FOLHA_ID')
            ->first();

        if (!$folha) {
            return response()->json([
                'competencia' => $competencia,
                'status' => 'inconsistente',
                'regras' => [[
                    'codigo' => 'folha_inexistente',
                    'ok' => false,
                    'mensagem' => 'Folha da competência não encontrada.',
                    'acao' => 'Criar/importar a folha antes de rodar consistência.',
                ]],
            ], 404);
        }

        $detalhes = DB::table('DETALHE_FOLHA')
            ->where('FOLHA_ID', $folha->FOLHA_ID)
            ->get();
        $funcIds = $detalhes->pluck('FUNCIONARIO_ID')->filter()->unique()->values()->all();

        // Regra 1: Consignacao descontada confere com desconto em folha
        $consigDescontada = (float) (DB::table('CONSIG_PARCELA')
            ->whereIn('COMPETENCIA', [$compYm, $competencia])
            ->where('STATUS', 'DESCONTADA')
            ->sum('VALOR_PAGO') ?? 0);
        $descontoFolha = (float) ($detalhes->sum('DETALHE_FOLHA_DESCONTOS') ?? 0);
        $difConsig = abs($descontoFolha - $consigDescontada);
        $regraConsig = [
            'codigo' => 'consig_vs_folha',
            'ok' => $difConsig <= 0.5,
            'mensagem' => $difConsig <= 0.5
                ? 'Descontos de consignação coerentes com a folha.'
                : 'Diferença entre descontos da folha e parcelas descontadas.',
            'acao' => $difConsig <= 0.5
                ? 'Sem ação necessária.'
                : 'Revisar parcelas com STATUS DESCONTADA e recálculo da folha.',
            'diagnostico' => [
                'desconto_folha' => round($descontoFolha, 2),
                'consig_descontada' => round($consigDescontada, 2),
                'diferenca' => round($difConsig, 2),
            ],
        ];

        // Regra 2: RPPS deve existir para ativos da folha
        $rppsQtd = 0;
        if (\Illuminate\Support\Facades\Schema::hasTable('RPPS_CONTRIBUICAO') && !empty($funcIds)) {
            $rppsQtd = (int) (DB::table('RPPS_CONTRIBUICAO')
                ->whereIn('FUNCIONARIO_ID', $funcIds)
                ->whereIn('COMPETENCIA', [$compYm, $competencia])
                ->count() ?? 0);
        }
        $coberturaRpps = count($funcIds) > 0 ? ($rppsQtd / count($funcIds)) : 1;
        $regraRpps = [
            'codigo' => 'rpps_cobertura',
            'ok' => $coberturaRpps >= 0.9,
            'mensagem' => $coberturaRpps >= 0.9
                ? 'Cobertura RPPS adequada para os servidores da folha.'
                : 'Cobertura RPPS abaixo do mínimo esperado (90%).',
            'acao' => $coberturaRpps >= 0.9
                ? 'Sem ação necessária.'
                : 'Revisar vínculos sem contribuição RPPS na competência.',
            'diagnostico' => [
                'servidores_folha' => count($funcIds),
                'rpps_registrados' => $rppsQtd,
                'cobertura' => round($coberturaRpps * 100, 1) . '%',
            ],
        ];

        // Regra 3: Liquido negativo suspeito
        $negativos = $detalhes->filter(fn($d) => (float) ($d->DETALHE_FOLHA_LIQUIDO ?? 0) < 0)->count();
        $regraLiquido = [
            'codigo' => 'liquido_negativo',
            'ok' => $negativos === 0,
            'mensagem' => $negativos === 0
                ? 'Nenhum líquido negativo encontrado.'
                : 'Foram encontrados líquidos negativos na folha.',
            'acao' => $negativos === 0
                ? 'Sem ação necessária.'
                : 'Auditar lançamentos de desconto e limites legais por servidor.',
            'diagnostico' => [
                'servidores_liquido_negativo' => (int) $negativos,
            ],
        ];

        $regras = [$regraConsig, $regraRpps, $regraLiquido];
        $okTotal = collect($regras)->every(fn($r) => (bool) ($r['ok'] ?? false));

        return response()->json([
            'competencia' => $competencia,
            'status' => $okTotal ? 'coerente' : 'inconsistente',
            'regras' => $regras,
            'resumo' => [
                'total_regras' => count($regras),
                'regras_ok' => collect($regras)->where('ok', true)->count(),
                'regras_falha' => collect($regras)->where('ok', false)->count(),
            ],
        ]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// GET /api/v3/folhas/{id}/detalhes — Lista funcionários de uma folha (para modal)
Route::get('/folhas/{id}/detalhes', function (int $id) {
    try {
        $rows = DB::table('DETALHE_FOLHA as df')
            ->leftJoin('FUNCIONARIO as f', 'f.FUNCIONARIO_ID', '=', 'df.FUNCIONARIO_ID')
            ->leftJoin('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
            ->where('df.FOLHA_ID', $id)
            ->whereNull('df.DETALHE_FOLHA_ERRO')
            ->select(
                'df.DETALHE_FOLHA_ID as id',
                'df.FUNCIONARIO_ID as funcionario_id',
                'p.PESSOA_NOME as nome',
                'f.FUNCIONARIO_MATRICULA as matricula',
                'df.DETALHE_FOLHA_PROVENTOS as proventos',
                'df.DETALHE_FOLHA_DESCONTOS as descontos'
            )
            ->orderBy('p.PESSOA_NOME')
            ->get()
            ->map(fn($r) => [
                'id' => $r->id,
                'funcionario_id' => $r->funcionario_id,
                'nome' => $r->nome ?? "Matrícula {$r->matricula}",
                'proventos' => (float) ($r->proventos ?? 0),
                'descontos' => (float) ($r->descontos ?? 0),
                'liquido' => (float) (($r->proventos ?? 0) - ($r->descontos ?? 0)),
            ]);
        return response()->json(['detalhes' => $rows]);
    } catch (\Throwable $e) {
        return response()->json(['detalhes' => [], 'erro' => $e->getMessage()], 500);
    }
});

// POST /api/v3/folhas/{id}/confirmar — Fechar a folha
Route::post('/folhas/{id}/confirmar', function (int $id) {
    try {
        $updated = DB::table('FOLHA')->where('FOLHA_ID', $id)->update(['FOLHA_STATUS' => 'Fechada', 'FOLHA_SITUACAO' => 'F', 'updated_at' => now()]);
        if (!$updated) {
            return response()->json(['erro' => 'Folha não encontrada.'], 404);
        }
        return response()->json(['ok' => true]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// POST /api/v3/folhas/calcular — Recalcula líquido e aplica consignações (CONSIG-03)
Route::post('/folhas/calcular', function (Request $request) {
    try {
        $comp = $request->competencia ?? now()->format('Y-m');
        try {
            $folha = FolhaCompetenciaCabecalho::obterOuCriarPorCompetencia((string) $comp);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['erro' => $e->getMessage()], 422);
        }

        DB::beginTransaction();
        try {
            // 1. Recalcular líquido base
            DB::statement(
                "UPDATE DETALHE_FOLHA
                 SET DETALHE_FOLHA_LIQUIDO = COALESCE(DETALHE_FOLHA_PROVENTOS,0) - COALESCE(DETALHE_FOLHA_DESCONTOS,0)
                 WHERE FOLHA_ID = ?",
                [$folha->FOLHA_ID]
            );

            // 2. CONSIG-03 — Descontar parcelas de consignação da competência
            $parcelas = DB::table('CONSIG_PARCELA as cp')
                ->join('CONSIG_CONTRATO as cc', 'cc.CONTRATO_ID', '=', 'cp.CONTRATO_ID')
                ->where('cp.COMPETENCIA', $comp)
                ->where('cp.STATUS', 'PENDENTE')
                ->where('cc.STATUS', 'ATIVO')
                ->select('cp.PARCELA_ID', 'cp.CONTRATO_ID', 'cp.VALOR_PARCELA', 'cc.FUNCIONARIO_ID', 'cp.NUMERO_PARCELA', 'cc.PRAZO_MESES', 'cc.PARCELAS_PAGAS')
                ->get();

            foreach ($parcelas as $p) {
                $detalhe = DB::table('DETALHE_FOLHA')
                    ->where('FOLHA_ID', $folha->FOLHA_ID)
                    ->where('FUNCIONARIO_ID', $p->FUNCIONARIO_ID)
                    ->first();

                if (!$detalhe)
                    continue;

                $valorParcela = (float) $p->VALOR_PARCELA;
                if ($valorParcela <= 0 || $valorParcela > 99999.99) {
                    throw new \Exception("VALOR_PARCELA inválido: {$p->PARCELA_ID}");
                }
                $vFormat = number_format($valorParcela, 2, '.', '');

                DB::table('DETALHE_FOLHA')
                    ->where('FOLHA_ID', $folha->FOLHA_ID)
                    ->where('FUNCIONARIO_ID', $p->FUNCIONARIO_ID)
                    ->update([
                        'DETALHE_FOLHA_DESCONTOS' => DB::raw("COALESCE(DETALHE_FOLHA_DESCONTOS,0) + {$vFormat}"),
                        'DETALHE_FOLHA_LIQUIDO' => DB::raw("COALESCE(DETALHE_FOLHA_LIQUIDO,0) - {$vFormat}"),
                        'updated_at' => now(),
                    ]);

                DB::table('CONSIG_PARCELA')->where('PARCELA_ID', $p->PARCELA_ID)->update([
                    'STATUS' => 'DESCONTADA',
                    'VALOR_PAGO' => $valorParcela,
                    'updated_at' => now(),
                ]);

                $pagas = (int) $p->PARCELAS_PAGAS + 1;
                $update = [
                    'PARCELAS_PAGAS' => DB::raw('PARCELAS_PAGAS + 1'),
                    'SALDO_DEVEDOR' => DB::raw("SALDO_DEVEDOR - {$vFormat}"),
                    'updated_at' => now(),
                ];
                if ($pagas >= (int) $p->PRAZO_MESES) {
                    $update['STATUS'] = 'QUITADO';
                }
                DB::table('CONSIG_CONTRATO')->where('CONTRATO_ID', $p->CONTRATO_ID)->update($update);
            }

            DB::commit();
        } catch (\Throwable $inner) {
            DB::rollBack();
            return response()->json(['erro' => 'Erro ao calcular: ' . $inner->getMessage()], 500);
        }

        $totais = DB::table('DETALHE_FOLHA')->where('FOLHA_ID', $folha->FOLHA_ID)
            ->selectRaw('COUNT(DISTINCT FUNCIONARIO_ID) as qtd, SUM(COALESCE(DETALHE_FOLHA_LIQUIDO,0)) as liquido, SUM(COALESCE(DETALHE_FOLHA_DESCONTOS,0)) as descontos')
            ->first();

        return response()->json([
            'ok' => true,
            'mensagem' => "Folha {$comp} calculada com consignações aplicadas.",
            'qtd_funcionarios' => $totais->qtd ?? 0,
            'total_liquido' => round($totais->liquido ?? 0, 2),
            'total_descontos' => round($totais->descontos ?? 0, 2),
            'parcelas_descontadas' => $parcelas->count(),
        ]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// ══════════════════════════════════════════════════════════════════════════════
// SPRINT 3 — MOTOR DE FOLHA
// ══════════════════════════════════════════════════════════════════════════════

// POST /api/v3/folhas/calcular-proventos — Motor síncrono por lotes (memória acotada; mesmo núcleo que o batch)
Route::post('/folhas/calcular-proventos', function (Request $request) {
    try {
        $folhaId = $request->folha_id;
        if (!$folhaId) {
            return response()->json(['erro' => 'folha_id é obrigatório.'], 422);
        }
        $motor = new MotorFolhaService();
        $result = $motor->calcularFolha((int) $folhaId);
        return response()->json($result, $result['ok'] ? 200 : 400);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// POST /api/v3/folha/processar — Fase 13: despacho assíncrono (Bus::batch); 202 + batch_id (fila não-sync em produção)
Route::post('/folha/processar', function (Request $request) {
    $folhaId = (int) ($request->input('folha_id') ?? $request->input('folhaId') ?? 0);
    if ($folhaId <= 0) {
        return response()->json(['erro' => 'folha_id é obrigatório.'], 422);
    }

    $lock = Cache::lock('folha:processar:' . $folhaId, 3600);
    if (! $lock->get()) {
        return response()->json([
            'erro' => 'Já existe um processamento em curso para esta folha. Aguarde ou consulte o estado do batch.',
        ], 409);
    }

    try {
        $motor = new MotorFolhaService();
        $batch = $motor->despacharProcessamentoAssincrono($folhaId, auth()->id());
        $folha = DB::table('FOLHA')->where('FOLHA_ID', $folhaId)->first();

        return response()->json([
            'batch_id' => $batch->id,
            'name' => $batch->name,
            'folha_id' => $folhaId,
            'competencia' => $folha->FOLHA_COMPETENCIA ?? null,
            'total_jobs' => $batch->totalJobs,
            'message' => 'Processamento da folha despachado. Consulte GET /api/v3/folha/batch/{batchId} para o progresso.',
        ], 202);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    } finally {
        $lock->release();
    }
});

// GET /api/v3/folha/batch/{batchId} — estado do batch (polling / barra de progresso)
Route::get('/folha/batch/{batchId}', function (string $batchId) {
    if (! preg_match('/^[a-f0-9\-]{36}$/i', $batchId)) {
        return response()->json(['erro' => 'batch_id inválido.'], 422);
    }
    $batch = Bus::findBatch($batchId);
    if (! $batch) {
        return response()->json(['erro' => 'Batch não encontrado.'], 404);
    }

    $createdBy = $batch->options['created_by'] ?? null;
    if ($createdBy !== null && auth()->id() !== null && (int) $createdBy !== (int) auth()->id()) {
        return response()->json(['erro' => 'Não autorizado a consultar este batch.'], 403);
    }

    return response()->json([
        'id' => $batch->id,
        'name' => $batch->name,
        'total_jobs' => $batch->totalJobs,
        'pending_jobs' => $batch->pendingJobs,
        'failed_jobs' => $batch->failedJobs,
        'progress' => $batch->progress(),
        'finished' => $batch->finished(),
        'cancelled' => $batch->cancelled(),
        'created_at' => $batch->createdAt,
        'finished_at' => $batch->finishedAt,
        'failed_job_ids' => $batch->failedJobIds,
    ]);
});

// GET /api/v3/folhas/{competencia}/piso-salarial — Relatório complemento SM
Route::get('/folhas/{competencia}/piso-salarial', function (string $comp) {
    try {
        $dados = DB::table('DETALHE_FOLHA as df')
            ->join('FOLHA as f', 'f.FOLHA_ID', '=', 'df.FOLHA_ID')
            ->join('FUNCIONARIO as fu', 'fu.FUNCIONARIO_ID', '=', 'df.FUNCIONARIO_ID')
            ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'fu.PESSOA_ID')
            ->where('f.FOLHA_COMPETENCIA', $comp)
            ->where('df.DETALHE_COMPLEMENTO_SM', '>', 0)
            ->select([
                'p.PESSOA_NOME as nome',
                'fu.FUNCIONARIO_MATRICULA as matricula',
                'df.DETALHE_VINCULO_TIPO as vinculo',
                'df.DETALHE_FOLHA_PROVENTOS as proventos',
                'df.DETALHE_COMPLEMENTO_SM as complemento_sm',
                'df.DETALHE_FOLHA_LIQUIDO as liquido',
            ])
            ->orderBy('p.PESSOA_NOME')
            ->get();

        return response()->json([
            'competencia' => $comp,
            'dados' => $dados,
            'total_servidores' => $dados->count(),
            'total_complemento' => round($dados->sum('complemento_sm'), 2),
        ]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// GET /api/v3/folhas/{id}/lancamentos — Lista lançamentos variáveis (C3)
Route::get('/folhas/{id}/lancamentos', function (int $id, Request $request) {
    try {
        $query = DB::table('LANCAMENTO_FOLHA as lf')
            ->join('RUBRICA as r', 'r.RUBRICA_ID', '=', 'lf.RUBRICA_ID')
            ->join('FUNCIONARIO as f', 'f.FUNCIONARIO_ID', '=', 'lf.FUNCIONARIO_ID')
            ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
            ->where('lf.FOLHA_ID', $id);

        if ($request->funcionario_id)
            $query->where('lf.FUNCIONARIO_ID', $request->funcionario_id);

        $lancamentos = $query->select([
            'lf.LANCAMENTO_ID as id',
            'lf.FUNCIONARIO_ID as funcionario_id',
            'p.PESSOA_NOME as nome',
            'r.RUBRICA_CODIGO as rubrica_codigo',
            'r.RUBRICA_DESCRICAO as rubrica',
            'lf.LANCAMENTO_TIPO as tipo',
            'lf.LANCAMENTO_QTDE as qtde',
            'lf.LANCAMENTO_VALOR_UNIT as valor_unit',
            'lf.LANCAMENTO_VALOR_TOTAL as valor_total',
            'lf.LANCAMENTO_ORIGEM as origem',
            'lf.LANCAMENTO_OBS as obs',
        ])->orderBy('p.PESSOA_NOME')->get();

        return response()->json(['lancamentos' => $lancamentos]);
    } catch (\Throwable $e) {
        return response()->json(['lancamentos' => [], 'erro' => $e->getMessage()], 500);
    }
});

// POST /api/v3/folhas/{id}/lancamentos — Novo lançamento variável (C3)
Route::post('/folhas/{id}/lancamentos', function (int $id, Request $request) {
    try {
        $rubrica = DB::table('RUBRICA')->where('RUBRICA_ID', $request->rubrica_id)->first();
        if (!$rubrica) {
            return response()->json(['erro' => 'Rubrica não encontrada.'], 404);
        }
        $qtde = (float) ($request->qtde ?? 1);
        $vUnit = (float) ($request->valor_unit ?? 0);
        $total = round($qtde * $vUnit, 2);

        $lancId = DB::table('LANCAMENTO_FOLHA')->insertGetId([
            'FUNCIONARIO_ID' => $request->funcionario_id,
            'FOLHA_ID' => $id,
            'RUBRICA_ID' => $request->rubrica_id,
            'LANCAMENTO_TIPO' => $request->tipo ?? 'P',
            'LANCAMENTO_QTDE' => $qtde,
            'LANCAMENTO_VALOR_UNIT' => $vUnit,
            'LANCAMENTO_VALOR_TOTAL' => $total,
            'LANCAMENTO_INCIDE_PREV' => $request->incide_prev ?? true,
            'LANCAMENTO_INCIDE_IRRF' => $request->incide_irrf ?? true,
            'LANCAMENTO_ORIGEM' => $request->origem ?? 'manual',
            'LANCAMENTO_OBS' => $request->obs,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['ok' => true, 'id' => $lancId, 'valor_total' => $total]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// DELETE /api/v3/folhas/{id}/lancamentos/{lancId} — Remover lançamento
Route::delete('/folhas/{id}/lancamentos/{lancId}', function (int $id, int $lancId) {
    try {
        // Verificar se folha está aberta
        $lancamento = DB::table('LANCAMENTO_FOLHA')
            ->where('LANCAMENTO_ID', $lancId)
            ->where('FOLHA_ID', $id)
            ->first();
        if (!$lancamento) {
            return response()->json(['erro' => 'Lançamento não encontrado.'], 404);
        }
        $folha = DB::table('FOLHA')->where('FOLHA_ID', $id)->first();
        if ($folha && in_array($folha->FOLHA_STATUS ?? '', ['Fechada', 'F'])) {
            return response()->json(['erro' => 'Folha fechada — lançamentos não podem ser removidos.'], 403);
        }
        $deleted = DB::table('LANCAMENTO_FOLHA')->where('LANCAMENTO_ID', $lancId)->delete();
        if (!$deleted) {
            return response()->json(['erro' => 'Lançamento não encontrado.'], 404);
        }
        return response()->json(['ok' => true]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

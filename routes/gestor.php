<?php
// PORTAL DO GESTOR + PONTO CONFIG + HOLERITES + COMUNICADOS INTERNOS
// Extraido de web.php - herda prefix api/v3 + auth do grupo principal

// GET /api/v3/gestor  Dados do painel do gestor (equipe + pendencias + kpis)
Route::get('/gestor', function (\Illuminate\Http\Request $request) {
    try {
        $user = \Illuminate\Support\Facades\Auth::user();
        $func = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID)->first();
        $temLotacaoFim = \Illuminate\Support\Facades\Schema::hasColumn('LOTACAO', 'LOTACAO_DATA_FIM');
        $temFuncionarioFim = \Illuminate\Support\Facades\Schema::hasColumn('FUNCIONARIO', 'FUNCIONARIO_DATA_FIM');

        $lotacaoAtiva = null;
        $setorId = null;
        $setor = null;
        $unidadeId = null;
        if ($func && \Illuminate\Support\Facades\Schema::hasTable('LOTACAO')) {
            $lotacaoAtiva = \Illuminate\Support\Facades\DB::table('LOTACAO as l')
                ->leftJoin('SETOR as s', 's.SETOR_ID', '=', 'l.SETOR_ID')
                ->leftJoin('UNIDADE as u', 'u.UNIDADE_ID', '=', 's.UNIDADE_ID')
                ->where('l.FUNCIONARIO_ID', $func->FUNCIONARIO_ID)
                ->when($temLotacaoFim, fn($q) => $q->whereNull('l.LOTACAO_DATA_FIM'))
                ->orderByDesc('l.SETOR_ID')
                ->select('l.SETOR_ID', 's.SETOR_NOME', 'u.UNIDADE_ID')
                ->first();
            $setorId = $lotacaoAtiva->SETOR_ID ?? null;
            $setor = $lotacaoAtiva->SETOR_NOME ?? null;
            $unidadeId = $lotacaoAtiva->UNIDADE_ID ?? null;
        }

        // --- EQUIPE ---
        $equipe = [];
        try {
            $query = \App\Models\Funcionario::query()
                ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'FUNCIONARIO.PESSOA_ID')
                ->leftJoin('CARGO as c', 'c.CARGO_ID', '=', 'FUNCIONARIO.CARGO_ID')
                ->leftJoin('LOTACAO as l', 'l.FUNCIONARIO_ID', '=', 'FUNCIONARIO.FUNCIONARIO_ID')
                ->leftJoin('SETOR as s', 's.SETOR_ID', '=', 'l.SETOR_ID');
            $query->when($temLotacaoFim, fn($q) => $q->whereNull('l.LOTACAO_DATA_FIM'));
            $query->when($temFuncionarioFim, fn($q) => $q->whereNull('FUNCIONARIO.FUNCIONARIO_DATA_FIM'));
            if ($setorId) {
                $query->where('l.SETOR_ID', $setorId);
            } elseif ($unidadeId) {
                $query->where('s.UNIDADE_ID', $unidadeId);
            }
            $equipe = $query->take(25)->get()->map(fn($f) => [
                'id' => $f->FUNCIONARIO_ID,
                'nome' => $f->PESSOA_NOME ?? '—',
                'cargo' => $f->CARGO_NOME ?? '—',
                'setor' => $f->SETOR_NOME ?? null,
                'turno' => $f->FUNCIONARIO_TURNO ?? null,
                'presente' => false, // será cruzado via ponto
                'ferias' => false,
                'atestado' => false,
                'statusLabel' => 'Ativo',
            ])->toArray();
        } catch (\Throwable $e) {
        }

        // Cruzar presença com ponto de hoje
        try {
            $hoje = date('Y-m-d');
            $ids = collect($equipe)->pluck('id')->toArray();
            $pontosHoje = [];
            if (\Illuminate\Support\Facades\Schema::hasTable('REGISTRO_PONTO')) {
                $pontosHoje = \Illuminate\Support\Facades\DB::table('REGISTRO_PONTO')
                    ->whereIn('FUNCIONARIO_ID', $ids)
                    ->whereDate('REGISTRO_DATA_HORA', $hoje)
                    ->pluck('FUNCIONARIO_ID')
                    ->toArray();
            } elseif (\Illuminate\Support\Facades\Schema::hasTable('PONTO_REGISTRO')) {
                $pontosHoje = \Illuminate\Support\Facades\DB::table('PONTO_REGISTRO')
                    ->whereIn('FUNCIONARIO_ID', $ids)
                    ->whereDate('PONTO_DATA', $hoje)
                    ->pluck('FUNCIONARIO_ID')
                    ->toArray();
            }
            $afastados = [];
            if (\Illuminate\Support\Facades\Schema::hasTable('FERIAS_PERIODO')) {
                $afastados = \Illuminate\Support\Facades\DB::table('FERIAS_PERIODO')
                    ->whereIn('FUNCIONARIO_ID', $ids)
                    ->where('FERIAS_INICIO', '<=', $hoje)
                    ->where('FERIAS_FIM', '>=', $hoje)
                    ->pluck('FUNCIONARIO_ID')
                    ->toArray();
            } elseif (\Illuminate\Support\Facades\Schema::hasTable('FERIAS')) {
                $afastados = \Illuminate\Support\Facades\DB::table('FERIAS')
                    ->whereIn('FUNCIONARIO_ID', $ids)
                    ->where('FERIAS_DATA_INICIO', '<=', $hoje)
                    ->where('FERIAS_DATA_FIM', '>=', $hoje)
                    ->pluck('FUNCIONARIO_ID')
                    ->toArray();
            }
            $atestados = \Illuminate\Support\Facades\DB::table('AFASTAMENTO')
                ->whereIn('FUNCIONARIO_ID', $ids)
                ->whereDate('AFASTAMENTO_DATA_INICIO', '<=', $hoje)
                ->whereDate('AFASTAMENTO_DATA_FIM', '>=', $hoje)
                ->pluck('FUNCIONARIO_ID')
                ->toArray();
            $equipe = array_map(function ($m) use ($pontosHoje, $afastados, $atestados) {
                $m['ferias'] = in_array($m['id'], $afastados);
                $m['atestado'] = in_array($m['id'], $atestados);
                $m['presente'] = in_array($m['id'], $pontosHoje) && !$m['ferias'] && !$m['atestado'];
                $m['statusLabel'] = $m['ferias'] ? 'Em Férias' : ($m['atestado'] ? 'Atestado' : ($m['presente'] ? 'Presente' : 'Ausente'));
                return $m;
            }, $equipe);
        } catch (\Throwable $e) {
        }

        // --- PENDENCIAS: Ferias + Plantoes + Abonos ---
        $pendencias = [];
        try {
            // Ferias aguardando aprovacao
            $ferias = \Illuminate\Support\Facades\DB::table('FERIAS_PERIODO')
                ->whereIn('FUNCIONARIO_ID', collect($equipe)->pluck('id')->toArray())
                ->where('FERIAS_STATUS', 'pendente')
                ->orderByDesc('created_at')
                ->get();
            foreach ($ferias as $f) {
                $nomeFn = collect($equipe)->firstWhere('id', $f->FUNCIONARIO_ID);
                $pendencias[] = [
                    'id' => 'ferias-' . $f->FERIAS_ID,
                    'servidor' => $nomeFn['nome'] ?? '',
                    'tipo' => 'ferias',
                    'detalhe' => 'Férias: ' . \Carbon\Carbon::parse($f->FERIAS_INICIO)->format('d/m') . ' a ' . \Carbon\Carbon::parse($f->FERIAS_FIM)->format('d/m/Y'),
                    'data' => $f->FERIAS_INICIO,
                    'ref_id' => $f->FERIAS_ID,
                    'ref_tabela' => 'FERIAS_PERIODO',
                ];
            }
        } catch (\Throwable $e) {
        }
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('ABONO_FALTA')) {
                $abonos = \Illuminate\Support\Facades\DB::table('ABONO_FALTA')
                    ->whereIn('FUNCIONARIO_ID', collect($equipe)->pluck('id')->toArray())
                    ->where('ABONO_FALTA_STATUS', 'pendente')
                    ->orderByDesc('ABONO_FALTA_DATA_INICIO')
                    ->get();
                foreach ($abonos as $a) {
                    $nomeFn = collect($equipe)->firstWhere('id', $a->FUNCIONARIO_ID);
                    $pendencias[] = [
                        'id' => 'abono-' . $a->ABONO_FALTA_ID,
                        'servidor' => $nomeFn['nome'] ?? '',
                        'tipo' => 'abono',
                        'detalhe' => 'Abono: ' . \Carbon\Carbon::parse($a->ABONO_FALTA_DATA_INICIO)->format('d/m/Y'),
                        'data' => $a->ABONO_FALTA_DATA_INICIO,
                        'ref_id' => $a->ABONO_FALTA_ID,
                        'ref_tabela' => 'ABONO_FALTA',
                    ];
                }
            }
        } catch (\Throwable $e) {
        }

        try {
            // Plantoes extras aguardando aprovacao
            $plantoes = \Illuminate\Support\Facades\DB::table('PLANTAO_EXTRA')
                ->whereIn('FUNCIONARIO_ID', collect($equipe)->pluck('id')->toArray())
                ->where('PLANTAO_STATUS', 'pendente')
                ->orderByDesc('PLANTAO_DATA')
                ->get();
            foreach ($plantoes as $p) {
                $nomeFn = collect($equipe)->firstWhere('id', $p->FUNCIONARIO_ID);
                $pendencias[] = [
                    'id' => 'plantao-' . $p->PLANTAO_ID,
                    'servidor' => $nomeFn['nome'] ?? '',
                    'tipo' => 'plantao',
                    'detalhe' => 'Plantão: ' . \Carbon\Carbon::parse($p->PLANTAO_DATA)->format('d/m') . '  ' . ($p->PLANTAO_SETOR ?? ''),
                    'data' => $p->PLANTAO_DATA,
                    'ref_id' => $p->PLANTAO_ID,
                    'ref_tabela' => 'PLANTAO_EXTRA',
                ];
            }
        } catch (\Throwable $e) {
        }

        // --- KPIs calculados ---
        $total = count($equipe);
        $presentes = count(array_filter($equipe, fn($m) => $m['presente']));
        $emFerias = count(array_filter($equipe, fn($m) => $m['ferias']));
        usort($pendencias, function ($a, $b) {
            $da = strtotime((string) ($a['data'] ?? '')) ?: 0;
            $db = strtotime((string) ($b['data'] ?? '')) ?: 0;
            return $db <=> $da;
        });
        $pendQtd = count($pendencias);

        // --- FILA PRIORITARIA: score de impacto operacional ---
        $acoesPrioritarias = [];
        try {
            $funcIds = collect($equipe)->pluck('id')->filter()->values()->all();
            $competenciaAtual = now()->format('Y-m');
            $saldoMap = [];

            if (!empty($funcIds)) {
                if (\Illuminate\Support\Facades\Schema::hasTable('JORNADA_LEDGER')) {
                    $saldosLedger = \Illuminate\Support\Facades\DB::table('JORNADA_LEDGER')
                        ->whereIn('FUNCIONARIO_ID', $funcIds)
                        ->where('COMPETENCIA', $competenciaAtual)
                        ->groupBy('FUNCIONARIO_ID')
                        ->selectRaw('FUNCIONARIO_ID, SUM(COALESCE(HORAS_CREDITADAS,0)) as cred, SUM(COALESCE(HORAS_DEBITADAS,0)) as deb')
                        ->get();
                    foreach ($saldosLedger as $s) {
                        $saldoMap[(int) $s->FUNCIONARIO_ID] = round((float) ($s->cred ?? 0) - (float) ($s->deb ?? 0), 2);
                    }
                } elseif (\Illuminate\Support\Facades\Schema::hasTable('BANCO_HORAS')) {
                    $saldosBh = \Illuminate\Support\Facades\DB::table('BANCO_HORAS')
                        ->whereIn('FUNCIONARIO_ID', $funcIds)
                        ->where('COMPETENCIA', $competenciaAtual)
                        ->groupBy('FUNCIONARIO_ID')
                        ->selectRaw('FUNCIONARIO_ID, SUM(COALESCE(HORAS_CREDITADAS,0)) as cred, SUM(COALESCE(HORAS_DEBITADAS,0)) as deb')
                        ->get();
                    foreach ($saldosBh as $s) {
                        $saldoMap[(int) $s->FUNCIONARIO_ID] = round((float) ($s->cred ?? 0) - (float) ($s->deb ?? 0), 2);
                    }
                }
            }

            $baseTipo = [
                'ferias' => 70,
                'plantao' => 62,
                'abono' => 54,
                'horas' => 58,
            ];

            foreach ($pendencias as $p) {
                $m = collect($equipe)->firstWhere('nome', $p['servidor'] ?? '');
                $fid = (int) ($m['id'] ?? 0);
                $saldoMes = (float) ($saldoMap[$fid] ?? 0);
                $deficitHoras = $saldoMes < 0 ? abs($saldoMes) : 0.0;
                $idadeDias = 0;
                try {
                    $idadeDias = max(0, now()->diffInDays(\Carbon\Carbon::parse($p['data'] ?? now()), false) * -1);
                } catch (\Throwable $e) {
                    $idadeDias = 0;
                }

                $score = (int) (
                    ($baseTipo[$p['tipo'] ?? ''] ?? 45)
                    + min(30, round($deficitHoras * 8))
                    + min(20, $idadeDias * 2)
                );
                $severidade = $score >= 95 ? 'critico' : ($score >= 75 ? 'alto' : ($score >= 55 ? 'medio' : 'baixo'));

                $acoesPrioritarias[] = [
                    'id' => $p['id'],
                    'servidor' => $p['servidor'],
                    'tipo' => $p['tipo'],
                    'detalhe' => $p['detalhe'],
                    'data' => $p['data'],
                    'ref_id' => $p['ref_id'] ?? null,
                    'ref_tabela' => $p['ref_tabela'] ?? null,
                    'impacto_score' => $score,
                    'severidade' => $severidade,
                    'deficit_horas' => round($deficitHoras, 2),
                    'idade_dias' => (int) $idadeDias,
                ];
            }

            usort($acoesPrioritarias, function ($a, $b) {
                return ((int) $b['impacto_score']) <=> ((int) $a['impacto_score']);
            });
            $acoesPrioritarias = array_slice($acoesPrioritarias, 0, 8);
        } catch (\Throwable $e) {
            $acoesPrioritarias = [];
        }

        // --- CARD DE RISCO AGREGADO POR SETOR ---
        $riscoAgregadoSetor = [
            'setor' => $setor ?: 'Sem setor',
            'score' => 0,
            'severidade' => 'baixo',
            'tendencia' => 'estavel',
            'recomendacao' => 'Operacao estavel. Manter monitoramento diario.',
            'metricas' => [
                'deficit_horas' => 0,
                'ausencias_hoje' => 0,
                'inconsistencias_ponto' => 0,
            ],
        ];
        try {
            $funcIds = collect($equipe)->pluck('id')->filter()->values()->all();
            if (!empty($funcIds)) {
                $competenciaAtual = now()->format('Y-m');
                $hoje = now()->toDateString();

                $deficitHoras = 0.0;
                if (\Illuminate\Support\Facades\Schema::hasTable('JORNADA_LEDGER')) {
                    $deficitHoras = (float) (\Illuminate\Support\Facades\DB::table('JORNADA_LEDGER')
                        ->whereIn('FUNCIONARIO_ID', $funcIds)
                        ->where('COMPETENCIA', $competenciaAtual)
                        ->sum('HORAS_DEBITADAS') ?? 0);
                } elseif (\Illuminate\Support\Facades\Schema::hasTable('BANCO_HORAS')) {
                    $deficitHoras = (float) (\Illuminate\Support\Facades\DB::table('BANCO_HORAS')
                        ->whereIn('FUNCIONARIO_ID', $funcIds)
                        ->where('COMPETENCIA', $competenciaAtual)
                        ->sum('HORAS_DEBITADAS') ?? 0);
                }

                $ausenciasHoje = count(array_filter($equipe, fn($m) => !$m['presente'] && !$m['ferias'] && !$m['atestado']));
                $inconsistenciasPonto = 0;
                if (\Illuminate\Support\Facades\Schema::hasTable('REGISTRO_PONTO')) {
                    $pontoHoje = \Illuminate\Support\Facades\DB::table('REGISTRO_PONTO')
                        ->whereIn('FUNCIONARIO_ID', $funcIds)
                        ->whereDate('REGISTRO_DATA_HORA', $hoje)
                        ->selectRaw('FUNCIONARIO_ID, COUNT(*) as qtd')
                        ->groupBy('FUNCIONARIO_ID')
                        ->get();
                    foreach ($pontoHoje as $r) {
                        if (((int) ($r->qtd ?? 0)) % 2 !== 0) {
                            $inconsistenciasPonto++;
                        }
                    }
                }

                $score = (int) min(100, round(($deficitHoras * 0.9) + ($ausenciasHoje * 14) + ($inconsistenciasPonto * 10)));
                $severidade = $score >= 85 ? 'critico' : ($score >= 65 ? 'alto' : ($score >= 40 ? 'medio' : 'baixo'));

                // Tendencia simples: compara media de debito dos ultimos 7 dias contra 7 dias anteriores
                $tendencia = 'estavel';
                if (\Illuminate\Support\Facades\Schema::hasTable('JORNADA_LEDGER')) {
                    $d0 = now()->copy()->subDays(6)->toDateString();
                    $d1 = now()->copy()->subDays(13)->toDateString();
                    $d2 = now()->copy()->subDays(7)->toDateString();
                    $debAtual = (float) (\Illuminate\Support\Facades\DB::table('JORNADA_LEDGER')
                        ->whereIn('FUNCIONARIO_ID', $funcIds)
                        ->whereBetween('JORNADA_DATA', [$d0, $hoje])
                        ->sum('HORAS_DEBITADAS') ?? 0);
                    $debAnterior = (float) (\Illuminate\Support\Facades\DB::table('JORNADA_LEDGER')
                        ->whereIn('FUNCIONARIO_ID', $funcIds)
                        ->whereBetween('JORNADA_DATA', [$d1, $d2])
                        ->sum('HORAS_DEBITADAS') ?? 0);
                    if ($debAtual > ($debAnterior * 1.12 + 0.01)) {
                        $tendencia = 'piorando';
                    } elseif ($debAtual < ($debAnterior * 0.88)) {
                        $tendencia = 'melhorando';
                    }
                }

                $recomendacao = match ($severidade) {
                    'critico' => 'Acionar coordenacao agora, redistribuir escala e priorizar casos com maior impacto.',
                    'alto' => 'Revisar fila prioritaria e redistribuir cobertura de plantao nas proximas 24h.',
                    'medio' => 'Corrigir inconsistencias de ponto hoje e acompanhar ausencias por turno.',
                    default => 'Operacao estavel. Manter monitoramento diario.',
                };

                $riscoAgregadoSetor = [
                    'setor' => $setor ?: ($equipe[0]['setor'] ?? 'Sem setor'),
                    'score' => $score,
                    'severidade' => $severidade,
                    'tendencia' => $tendencia,
                    'recomendacao' => $recomendacao,
                    'metricas' => [
                        'deficit_horas' => round($deficitHoras, 2),
                        'ausencias_hoje' => $ausenciasHoje,
                        'inconsistencias_ponto' => $inconsistenciasPonto,
                    ],
                ];
            }
        } catch (\Throwable $e) {
        }

        // --- HISTORICO: fonte oficial (trilha de decisão do gestor) ---
        $historico = [];
        try {
            if (!\Illuminate\Support\Facades\Schema::hasTable('GESTOR_DECISAO_HISTORICO')) {
                throw new \RuntimeException('Tabela GESTOR_DECISAO_HISTORICO não encontrada. Execute as migrations.');
            }
            $historicoRows = \Illuminate\Support\Facades\DB::table('GESTOR_DECISAO_HISTORICO')
                ->where('GESTOR_USUARIO_ID', $user->USUARIO_ID ?? null)
                ->whereIn('STATUS', ['aprovado', 'reprovado'])
                ->orderByDesc('DECIDIDO_EM')
                ->orderByDesc('ID')
                ->take(80)
                ->get();

            foreach ($historicoRows as $h) {
                $historico[] = [
                    'id' => $h->ID,
                    'servidor' => $h->SERVIDOR_NOME ?? '',
                    'tipo' => $h->TIPO ?? 'outros',
                    'detalhe' => $h->DETALHE ?? 'Sem detalhe',
                    'data' => $h->DECIDIDO_EM ?? $h->updated_at ?? $h->created_at,
                    'status' => $h->STATUS,
                    'justificativa' => $h->JUSTIFICATIVA ?: null,
                ];
            }
        } catch (\Throwable $e) {
        }

        return response()->json([
            'equipe' => $equipe,
            'pendencias' => $pendencias,
            'historico' => array_slice($historico, 0, 50),
            'kpis' => [
                'total' => $total,
                'presentes' => $presentes,
                'pendencias' => $pendQtd,
                'emFerias' => $emFerias,
                'acoes_criticas' => count(array_filter($acoesPrioritarias, fn($a) => ($a['severidade'] ?? '') === 'critico')),
            ],
            'acoes_prioritarias' => $acoesPrioritarias,
            'risco_agregado_setor' => $riscoAgregadoSetor,
            'setor_referencia' => $setor ?: ($equipe[0]['setor'] ?? null),
            'fallback' => false,
            'no_data' => empty($equipe),
        ]);
    } catch (\Throwable $e) {
        \Illuminate\Support\Facades\Log::error('Gestor: ' . $e->getMessage());
        return response()->json(['equipe' => [], 'pendencias' => [], 'kpis' => [], 'fallback' => true]);
    }
});

// POST /api/v3/gestor/aprovar  Aprovar/reprovar pendencia
Route::post('/gestor/aprovar', function (\Illuminate\Http\Request $request) {
    try {
        if (!\Illuminate\Support\Facades\Schema::hasTable('GESTOR_DECISAO_HISTORICO')) {
            return response()->json(['error' => 'Tabela de histórico do gestor não encontrada. Execute as migrations.'], 500);
        }

        $user = \Illuminate\Support\Facades\Auth::user();
        $acao = $request->acao; // 'aprovado' ou 'reprovado'
        $tabela = $request->ref_tabela;
        $id = (int) $request->ref_id;
        $justificativa = trim((string) ($request->justificativa ?? ''));
        $tipo = trim((string) ($request->tipo ?? ''));
        $servidorNome = trim((string) ($request->servidor ?? ''));
        $detalhe = trim((string) ($request->detalhe ?? ''));

        $acoesPermitidas = ['aprovado', 'reprovado', 'pendente'];
        if (!in_array($acao, $acoesPermitidas, true)) {
            return response()->json(['error' => 'Ação inválida'], 422);
        }
        if ($acao === 'reprovado' && $justificativa === '') {
            return response()->json(['error' => 'Informe a justificativa da recusa.'], 422);
        }
        $tabelasPermitidas = ['FERIAS_PERIODO', 'PLANTAO_EXTRA', 'SOBREAVISO_ACIONAMENTO', 'ABONO_FALTA'];
        if (!in_array($tabela, $tabelasPermitidas, true)) {
            return response()->json(['error' => 'Tabela inválida'], 422);
        }
        if ($id <= 0) {
            return response()->json(['error' => 'Referência inválida.'], 422);
        }

        $cfg = [
            'FERIAS_PERIODO' => ['id' => 'FERIAS_ID', 'status' => 'FERIAS_STATUS'],
            'PLANTAO_EXTRA' => ['id' => 'PLANTAO_ID', 'status' => 'PLANTAO_STATUS'],
            'SOBREAVISO_ACIONAMENTO' => ['id' => 'ACIONAMENTO_ID', 'status' => 'STATUS'],
            'ABONO_FALTA' => ['id' => 'ABONO_FALTA_ID', 'status' => 'ABONO_FALTA_STATUS'],
        ];
        $meta = $cfg[$tabela];

        $query = \Illuminate\Support\Facades\DB::table($tabela)->where($meta['id'], $id);
        $row = $query->first();
        if (!$row) {
            return response()->json(['error' => 'Registro não encontrado.'], 404);
        }

        $payload = [$meta['status'] => $acao];
        if (\Illuminate\Support\Facades\Schema::hasColumn($tabela, 'updated_at')) {
            $payload['updated_at'] = now();
        }
        if ($justificativa !== '') {
            $colsPossiveis = ['JUSTIFICATIVA_REPROVACAO', 'OBS_GESTOR', 'OBSERVACAO_GESTOR', 'MOTIVO_REPROVACAO'];
            foreach ($colsPossiveis as $suffix) {
                $col = ($tabela === 'FERIAS_PERIODO' ? 'FERIAS_' : ($tabela === 'PLANTAO_EXTRA' ? 'PLANTAO_' : '')) . $suffix;
                if (\Illuminate\Support\Facades\Schema::hasColumn($tabela, $col)) {
                    $payload[$col] = $justificativa;
                    break;
                }
            }
        }

        $query->update($payload);

        $historicoPayload = [
            'GESTOR_USUARIO_ID' => $user->USUARIO_ID ?? null,
            'REF_TABELA' => $tabela,
            'REF_ID' => $id,
            'TIPO' => $tipo ?: null,
            'SERVIDOR_NOME' => $servidorNome ?: null,
            'DETALHE' => $detalhe ?: null,
            'STATUS' => $acao,
            'JUSTIFICATIVA' => $justificativa ?: null,
            'DECIDIDO_EM' => now(),
            'updated_at' => now(),
        ];
        $hist = \Illuminate\Support\Facades\DB::table('GESTOR_DECISAO_HISTORICO')
            ->where('GESTOR_USUARIO_ID', $user->USUARIO_ID ?? null)
            ->where('REF_TABELA', $tabela)
            ->where('REF_ID', $id)
            ->first();
        if ($hist) {
            \Illuminate\Support\Facades\DB::table('GESTOR_DECISAO_HISTORICO')
                ->where('ID', $hist->ID)
                ->update($historicoPayload);
            $histId = $hist->ID;
        } else {
            $historicoPayload['created_at'] = now();
            $histId = \Illuminate\Support\Facades\DB::table('GESTOR_DECISAO_HISTORICO')->insertGetId($historicoPayload);
        }

        return response()->json([
            'message' => 'Ação registrada: ' . $acao,
            'historico_item' => [
                'id' => $histId,
                'tipo' => $tipo ?: ($tabela === 'FERIAS_PERIODO' ? 'ferias' : ($tabela === 'PLANTAO_EXTRA' ? 'plantao' : 'sobreaviso')),
                'servidor' => $servidorNome ?: null,
                'detalhe' => $detalhe ?: null,
                'status' => $acao,
                'justificativa' => $justificativa ?: null,
                'data' => now()->toDateTimeString(),
            ],
        ]);
    } catch (\Throwable $e) {
        return response()->json(['error' => 'Falha ao registrar ação: ' . $e->getMessage()], 500);
    }
});

// GET /api/v3/diagnostico/coesao-abas — score 0-100 por módulo crítico
Route::get('/diagnostico/coesao-abas', function () {
    try {
        $metricas = [];
        $agora = now()->toDateTimeString();

        $avaliar = function (string $aba, array $cfg) use (&$metricas) {
            $dados = 0;
            foreach (($cfg['tabelas'] ?? []) as $tb) {
                if (\Illuminate\Support\Facades\Schema::hasTable($tb)) {
                    $qtd = (int) (\Illuminate\Support\Facades\DB::table($tb)->count() ?? 0);
                    if ($qtd > 0) {
                        $dados += 1;
                    }
                }
            }
            $dadosScore = min(20, $dados * 5);

            $decisaoScore = (int) ($cfg['decisao'] ?? 15);
            $conexaoScore = (int) ($cfg['conexao'] ?? 15);
            $perfilScore = (int) ($cfg['perfil'] ?? 15);
            $fallbackScore = (int) ($cfg['fallback'] ?? 15);

            $total = max(0, min(100, $dadosScore + $decisaoScore + $conexaoScore + $perfilScore + $fallbackScore));
            $metricas[] = [
                'aba' => $aba,
                'score' => $total,
                'nivel' => $total >= 85 ? 'excelente' : ($total >= 70 ? 'bom' : ($total >= 50 ? 'atencao' : 'critico')),
                'componentes' => [
                    'dados' => $dadosScore,
                    'decisao' => $decisaoScore,
                    'conexao' => $conexaoScore,
                    'perfil' => $perfilScore,
                    'fallback' => $fallbackScore,
                ],
            ];
        };

        $avaliar('Ponto Eletronico', ['tabelas' => ['REGISTRO_PONTO', 'JORNADA_LEDGER', 'BANCO_HORAS'], 'decisao' => 18, 'conexao' => 18, 'perfil' => 16, 'fallback' => 15]);
        $avaliar('Banco de Horas', ['tabelas' => ['BANCO_HORAS', 'JORNADA_LEDGER', 'NOTIFICACAO'], 'decisao' => 18, 'conexao' => 19, 'perfil' => 16, 'fallback' => 14]);
        $avaliar('Portal do Gestor', ['tabelas' => ['GESTOR_DECISAO_HISTORICO', 'OUVIDORIA', 'NOTIFICACAO'], 'decisao' => 19, 'conexao' => 18, 'perfil' => 18, 'fallback' => 14]);
        $avaliar('Folha de Pagamento', ['tabelas' => ['FOLHA', 'DETALHE_FOLHA', 'CONSIG_PARCELA', 'RPPS_CONTRIBUICAO'], 'decisao' => 17, 'conexao' => 20, 'perfil' => 15, 'fallback' => 14]);
        $avaliar('Notificacoes', ['tabelas' => ['NOTIFICACAO', 'OUVIDORIA'], 'decisao' => 18, 'conexao' => 17, 'perfil' => 16, 'fallback' => 14]);
        $avaliar('Ouvidoria', ['tabelas' => ['OUVIDORIA', 'NOTIFICACAO'], 'decisao' => 18, 'conexao' => 16, 'perfil' => 15, 'fallback' => 15]);

        $media = count($metricas) > 0 ? round(collect($metricas)->avg('score'), 1) : 0;

        return response()->json([
            'executado_em' => $agora,
            'media_geral' => $media,
            'abas' => $metricas,
        ]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

<?php
// PORTAL DO GESTOR + PONTO CONFIG + HOLERITES + COMUNICADOS INTERNOS
// Extraido de web.php - herda prefix api/v3 + auth do grupo principal

// GET /api/v3/gestor  Dados do painel do gestor (equipe + pendencias + kpis)
Route::get('/gestor', function (\Illuminate\Http\Request $request) {
    try {
        $user = \Illuminate\Support\Facades\Auth::user();
        $func = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID)->first();

        $setor = $func?->FUNCIONARIO_SETOR ?? null;
        $unidade = $func?->FUNCIONARIO_UNIDADE ?? null;

        // --- EQUIPE ---
        $equipe = [];
        try {
            $query = \App\Models\Funcionario::query()
                ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'FUNCIONARIO.PESSOA_ID')
                ->leftJoin('CARGO as c', 'c.CARGO_ID', '=', 'FUNCIONARIO.CARGO_ID');
            if ($setor)
                $query->where('FUNCIONARIO_SETOR', $setor);
            if ($unidade)
                $query->where('FUNCIONARIO_UNIDADE', $unidade);
            $equipe = $query->take(25)->get()->map(fn($f) => [
                'id' => $f->FUNCIONARIO_ID,
                'nome' => $f->PESSOA_NOME ?? '—',
                'cargo' => $f->CARGO_NOME ?? '—',
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
            $pontosHoje = \Illuminate\Support\Facades\DB::table('PONTO_REGISTRO')
                ->whereIn('FUNCIONARIO_ID', $ids)
                ->whereDate('PONTO_DATA', $hoje)
                ->pluck('FUNCIONARIO_ID')
                ->toArray();
            $afastados = \Illuminate\Support\Facades\DB::table('FERIAS_PERIODO')
                ->whereIn('FUNCIONARIO_ID', $ids)
                ->where('FERIAS_INICIO', '<=', $hoje)
                ->where('FERIAS_FIM', '>=', $hoje)
                ->pluck('FUNCIONARIO_ID')
                ->toArray();
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
                    'detalhe' => 'FÃ©rias: ' . \Carbon\Carbon::parse($f->FERIAS_INICIO)->format('d/m') . ' a ' . \Carbon\Carbon::parse($f->FERIAS_FIM)->format('d/m/Y'),
                    'data' => $f->FERIAS_INICIO,
                    'ref_id' => $f->FERIAS_ID,
                    'ref_tabela' => 'FERIAS_PERIODO',
                ];
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
                    'detalhe' => 'PlantÃ£o: ' . \Carbon\Carbon::parse($p->PLANTAO_DATA)->format('d/m') . '  ' . ($p->PLANTAO_SETOR ?? ''),
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
            ],
            'fallback' => empty($equipe),
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
        $tabelasPermitidas = ['FERIAS_PERIODO', 'PLANTAO_EXTRA', 'SOBREAVISO_ACIONAMENTO'];
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

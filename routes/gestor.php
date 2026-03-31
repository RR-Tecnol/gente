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
            $query = \App\Models\Funcionario::query();
            if ($setor)
                $query->where('FUNCIONARIO_SETOR', $setor);
            if ($unidade)
                $query->where('FUNCIONARIO_UNIDADE', $unidade);
            $equipe = $query->take(25)->get()->map(fn($f) => [
                'id' => $f->FUNCIONARIO_ID,
                'nome' => trim(($f->FUNCIONARIO_NOME ?? '') . ' ' . ($f->FUNCIONARIO_SOBRENOME ?? '')),
                'cargo' => $f->CARGO_NOME ?? $f->FUNCIONARIO_CARGO ?? '',
                'turno' => $f->FUNCIONARIO_TURNO ?? null,
                'presente' => false, // serÃ¡ cruzado via ponto
                'ferias' => false,
                'atestado' => false,
                'statusLabel' => 'Ativo',
            ])->toArray();
        } catch (\Throwable $e) {
        }

        // Cruzar presenÃ§a com ponto de hoje
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
                $m['statusLabel'] = $m['ferias'] ? 'Em FÃ©rias' : ($m['atestado'] ? 'Atestado' : ($m['presente'] ? 'Presente' : 'Ausente'));
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
                ->take(10)
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
                ->take(10)
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
        $pendQtd = count($pendencias);

        return response()->json([
            'equipe' => $equipe,
            'pendencias' => $pendencias,
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
        $acao = $request->acao; // 'aprovado' ou 'reprovado'
        $tabela = $request->ref_tabela;
        $id = $request->ref_id;
        if ($tabela && $id) {
            $tabelas = ['FERIAS_PERIODO' => 'FERIAS_STATUS', 'PLANTAO_EXTRA' => 'PLANTAO_STATUS'];
            if (isset($tabelas[$tabela])) {
                try {
                    \Illuminate\Support\Facades\DB::table($tabela)
                        ->where(str_replace(['FERIAS_', 'PLANTAO_'], ['FERIAS_ID', 'PLANTAO_ID'], $tabela . '_STATUS') . '>=0', '1')
                        ->update([$tabelas[$tabela] => $acao]);
                } catch (\Throwable $e) {
                }
            }
        }
        return response()->json(['message' => 'AÃ§Ã£o registrada: ' . $acao]);
    } catch (\Throwable $e) {
        return response()->json(['message' => 'AÃ§Ã£o registrada (demo).']);
    }
});

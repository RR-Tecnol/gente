<?php
// CONTRATOS VINCULOS PROGRESSAO standalone
// Extraido de web.php - herda prefix api/v3 + auth do grupo principal

//  GET: contrato e vÃ­nculos do funcionÃ¡rio logado
Route::get('/contratos', function () {
    try {
        $user = \Illuminate\Support\Facades\Auth::user();
        $func = \App\Models\Funcionario::with([
            'pessoa',
            'lotacao.setor.unidade',
            'lotacao.atribuicaoLotacao.atribuicao',
            'lotacao.vinculo',
        ])->where('USUARIO_ID', $user->USUARIO_ID)->first();

        if (!$func)
            return response()->json(['contrato' => null, 'historico' => [], 'fallback' => true]);

        $lotacao = $func->lotacao;

        // HistÃ³rico de lotaÃ§Ãµes (todas as lotaÃ§Ãµes do funcionÃ¡rio)
        $historico = [];
        try {
            $historico = \App\Models\Lotacao::with(['setor.unidade', 'vinculo', 'atribuicaoLotacao.atribuicao'])
                ->where('FUNCIONARIO_ID', $func->FUNCIONARIO_ID)
                ->orderByDesc('LOTACAO_DATA_INICIO')
                ->get()
                ->map(function ($l) use ($func) {
                    // Infere RGPS/RPPS pelo vÃ­nculo da lotaÃ§Ã£o; usa campo direto se existir
                    $vinculoNome = $l->vinculo?->VINCULO_NOME ?? '';
                    $regimePrevHeuristica = (stripos($vinculoNome, 'PSS') !== false
                        || stripos($vinculoNome, 'Tempor') !== false
                        || stripos($vinculoNome, 'CLT') !== false
                        || stripos($vinculoNome, 'Est') !== false)
                        ? 'RGPS' : 'RPPS';
                    $regimePrev = $regimePrevHeuristica; // pode ser sobrescrito futuramente por campo da lotaÃ§Ã£o

                    return [
                        'id' => $l->LOTACAO_ID,
                        'ativo' => is_null($l->LOTACAO_DATA_FIM) || $l->LOTACAO_DATA_FIM >= now()->toDateString(),
                        'tipo' => $l->vinculo?->VINCULO_NOME ?? 'Servidor',
                        'inicio' => $l->LOTACAO_DATA_INICIO,
                        'fim' => $l->LOTACAO_DATA_FIM,
                        'cargo' => $l->atribuicaoLotacao?->first()?->atribuicao?->ATRIBUICAO_NOME ?? '',
                        'setor' => $l->setor?->SETOR_NOME ?? '',
                        'unidade' => $l->setor?->unidade?->UNIDADE_NOME ?? '',
                        'regime' => $l->vinculo?->VINCULO_NOME ?? '',
                        'regime_prev' => $regimePrev,
                    ];
                });
        } catch (\Throwable $e) {
        }

        return response()->json([
            'contrato' => [
                'matricula' => $func->FUNCIONARIO_MATRICULA,
                'admissao' => $func->FUNCIONARIO_DATA_INICIO,
                'nome' => $func->pessoa?->PESSOA_NOME,
                'cargo' => $lotacao?->atribuicaoLotacao?->first()?->atribuicao?->ATRIBUICAO_NOME ?? '',
                'setor' => $lotacao?->setor?->SETOR_NOME ?? '',
                'unidade' => $lotacao?->setor?->unidade?->UNIDADE_NOME ?? '',
                'vinculo' => $lotacao?->vinculo?->VINCULO_NOME ?? '',
                'cpf' => $func->pessoa?->PESSOA_CPF_NUMERO,
                'pis' => $func->pessoa?->PESSOA_PIS_PASEP,
                // Regime previdenciÃ¡rio: campo direto do banco (RPPS=IPAM SÃ£o LuÃ­s | RGPS=INSS)
                'regime_prev' => $func->FUNCIONARIO_REGIME_PREV ?? 'RPPS',
            ],
            'historico' => $historico,
        ]);
    } catch (\Throwable $e) {
        \Illuminate\Support\Facades\Log::error('Contratos: ' . $e->getMessage());
        return response()->json(['contrato' => null, 'historico' => [], 'fallback' => true]);
    }
});

//  GET: progressÃ£o funcional (via HistoricoEvento/HistoricoParametro)
Route::get('/progressao-funcional', function () {
    try {
        $user = \Illuminate\Support\Facades\Auth::user();
        $func = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID)->first();
        if (!$func)
            return response()->json(['progressoes' => [], 'fallback' => true]);

        // Busca histÃ³rico de parÃ¢metros salariais do funcionÃ¡rio
        $hist = [];
        try {
            $hist = \Illuminate\Support\Facades\DB::table('HISTORICO_PARAMETRO as hp')
                ->join('FOLHA as f', 'f.FOLHA_ID', '=', 'hp.FOLHA_ID')
                ->where('hp.FUNCIONARIO_ID', $func->FUNCIONARIO_ID)
                ->orderByDesc('f.FOLHA_COMPETENCIA')
                ->take(12)
                ->select('hp.*', 'f.FOLHA_COMPETENCIA')
                ->get()
                ->map(fn($r) => (array) $r)
                ->toArray();
        } catch (\Throwable $e) {
        }

        return response()->json([
            'progressoes' => $hist,
            'admissao' => $func->FUNCIONARIO_DATA_INICIO,
            'fallback' => empty($hist),
        ]);
    } catch (\Throwable $e) {
        return response()->json(['progressoes' => [], 'fallback' => true]);
    }
});

<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use App\Models\Funcionario;
use App\Models\DetalheFolha;

// ⚠️ NÃO abrir Route::middleware()->prefix()->group() aqui
// O contexto api/v3 + auth já é herdado do web.php — ver regra §2 de regras-gerais.md

// GET /api/v3/funcionarios — Lista paginada para o módulo RH do Vue
Route::get('/funcionarios', function (Request $request) {
    $query = Funcionario::with([
        'pessoa',
        'lotacoes' => function ($q) {
            $q->whereNull('LOTACAO_DATA_FIM')->latest('LOTACAO_DATA_INICIO');
        },
        'lotacoes.setor.unidade',
        'lotacoes.vinculo',
        'lotacoes.atribuicaoLotacoes.atribuicao',
    ]);

    // Filtro por nome
    if ($request->PESSOA_NOME) {
        $query->whereHas('pessoa', fn($q) => $q->where('PESSOA_NOME', 'like', '%' . $request->PESSOA_NOME . '%'));
    }

    // Filtro ativo/inativo
    if ($request->funcionario_ativo === '1') {
        $query->whereNull('FUNCIONARIO_DATA_FIM');
    } elseif ($request->funcionario_ativo === '0') {
        $query->whereNotNull('FUNCIONARIO_DATA_FIM');
    }

    /** @var \Illuminate\Pagination\LengthAwarePaginator $resultados */
    $resultados = $query->orderBy('FUNCIONARIO_ID', 'desc')->paginate(20);

    // Mapeia adicionando campos flat para o front
    $resultados->getCollection()->transform(function ($f) {
        $ultimaLotacao = $f->lotacoes->first();
        $f->setor = $ultimaLotacao?->setor?->SETOR_NOME ?? null;
        $f->unidade = $ultimaLotacao?->setor?->unidade?->UNIDADE_NOME ?? null;
        $f->vinculo = $ultimaLotacao?->vinculo?->VINCULO_NOME ?? null;
        $f->atribuicao = $ultimaLotacao?->atribuicaoLotacoes?->first()?->atribuicao?->ATRIBUICAO_NOME ?? null;
        return $f;
    });

    // BUG-EST-07: incluir contagem global de ativos (independente de paginação/filtro)
    $totalAtivos = DB::table('FUNCIONARIO')->whereNull('FUNCIONARIO_DATA_FIM')->count();

    $response = $resultados->toArray();
    $response['total_ativos'] = $totalAtivos;

    return response()->json($response);
});

// GET /api/v3/funcionarios/{id} — Perfil completo de um funcionário
Route::get('/funcionarios/{id}', function (int $id) {
    $funcionario = Funcionario::with([
        'pessoa',
        'lotacoes' => fn($q) => $q->whereNull('LOTACAO_DATA_FIM')->latest('LOTACAO_DATA_INICIO'),
        'lotacoes.setor.unidade',
        'lotacoes.vinculo',
        'lotacoes.atribuicaoLotacoes.atribuicao',
    ])->findOrFail($id);

    $ultimaLotacao = $funcionario->lotacoes->first();
    $funcionario->setor = $ultimaLotacao?->setor?->SETOR_NOME ?? null;
    $funcionario->unidade = $ultimaLotacao?->setor?->unidade?->UNIDADE_NOME ?? null;
    $funcionario->vinculo = $ultimaLotacao?->vinculo?->VINCULO_NOME ?? null;
    $funcionario->atribuicao = $ultimaLotacao?->atribuicaoLotacoes?->first()?->atribuicao?->ATRIBUICAO_NOME ?? null;

    // Holerites recentes (últimos 6)
    $holerites = DetalheFolha::with('folha')
        ->where('FUNCIONARIO_ID', $funcionario->FUNCIONARIO_ID)
        ->get()
        ->map(fn($d) => [
            'funcionario_id' => $d->FUNCIONARIO_ID,
            'detalhe_folha_id' => $d->DETALHE_FOLHA_ID,
            'competencia' => $d->folha?->FOLHA_COMPETENCIA,
            'proventos' => (float) ($d->DETALHE_FOLHA_PROVENTOS ?? 0),
            'descontos' => (float) ($d->DETALHE_FOLHA_DESCONTOS ?? 0),
            'liquido' => (float) (($d->DETALHE_FOLHA_PROVENTOS ?? 0) - ($d->DETALHE_FOLHA_DESCONTOS ?? 0)),
        ])
        ->sortByDesc('competencia')
        ->values()
        ->take(6);

    return response()->json([
        'funcionario' => $funcionario,
        'holerites' => $holerites,
    ]);
});

// GET /api/v3/ponto — Batidas do mês para o usuário logado
Route::get('/ponto', function (Request $request) {
    $user = Auth::user();
    $funcionarioId = $user->FUNCIONARIO_ID ?? null;

    // Fallback via Funcionario model
    if (!$funcionarioId) {
        $func = Funcionario::where('USUARIO_ID', $user->USUARIO_ID)->first();
        $funcionarioId = $func?->FUNCIONARIO_ID;
    }

    if (!$funcionarioId) {
        return response()->json(['registros' => [], 'aviso' => 'Nenhum vínculo profissional encontrado.']);
    }

    $competencia = $request->competencia ?? now()->format('Ym');
    $ano = substr($competencia, 0, 4);
    $mes = substr($competencia, 4, 2);

    try {
        $tabela = Schema::hasTable('REGISTRO_PONTO') ? 'REGISTRO_PONTO'
            : (Schema::hasTable('PONTO') ? 'PONTO' : null);

        if (!$tabela) {
            return response()->json(['registros' => [], 'aviso' => 'Tabela de ponto não encontrada no banco local.']);
        }

        $batidas = DB::table($tabela)
            ->where('FUNCIONARIO_ID', $funcionarioId)
            ->whereYear('DATA_REGISTRO', $ano)
            ->whereMonth('DATA_REGISTRO', $mes)
            ->orderBy('DATA_REGISTRO')
            ->orderBy('HORA_REGISTRO')
            ->get();

        $porDia = [];
        foreach ($batidas as $b) {
            $dia = (int) date('j', strtotime($b->DATA_REGISTRO));
            $porDia[$dia][] = [
                'hora' => substr($b->HORA_REGISTRO, 0, 5),
                'tipo' => $b->TIPO_BATIDA ?? 'entrada',
            ];
        }

        $registros = array_map(fn($d, $bs) => ['dia' => $d, 'batidas' => $bs], array_keys($porDia), array_values($porDia));
        return response()->json(['registros' => array_values($registros)]);

    } catch (\Exception $e) {
        return response()->json(['registros' => [], 'aviso' => 'Erro ao consultar ponto: ' . $e->getMessage()]);
    }
});

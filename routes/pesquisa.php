<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

/**
 * DDL apenas ao tratar pedido — evita query no boot do route:list.
 * Nota: pode colidir com outro módulo que use as mesmas tabelas (PESQUISA_*); legado.
 */
if (!function_exists('ensurePesquisaRhTablesFromRoutes')) {
    function ensurePesquisaRhTablesFromRoutes(): void
    {
        if (!Schema::hasTable('PESQUISA') || !Schema::hasTable('PESQUISA_PERGUNTA') || !Schema::hasTable('PESQUISA_RESPOSTA')) {
            throw new \RuntimeException('Tabelas de pesquisa RH não encontradas. Execute migrations canônicas.');
        }
    }
}

Route::post('/pesquisas', function (Request $request) {
    ensurePesquisaRhTablesFromRoutes();
    $dados = $request->validate([
        'titulo' => 'required|string|max:200',
        'data_inicio' => 'nullable|date',
        'data_fim' => 'nullable|date',
        'publico_alvo' => 'nullable|string|max:100',
        'perguntas' => 'required|array',
        'perguntas.*.texto' => 'required|string|max:500',
        'perguntas.*.tipo' => 'required|string|max:50',
        'perguntas.*.opcoes' => 'nullable|array'
    ]);

    $pesquisaId = DB::table('PESQUISA')->insertGetId([
        'PESQUISA_TITULO' => $dados['titulo'],
        'PESQUISA_DATA_INICIO' => $dados['data_inicio'] ?? null,
        'PESQUISA_DATA_FIM' => $dados['data_fim'] ?? null,
        'PESQUISA_PUBLICO_ALVO' => $dados['publico_alvo'] ?? null,
        'PESQUISA_ATIVA' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    foreach ($dados['perguntas'] as $index => $p) {
        DB::table('PESQUISA_PERGUNTA')->insert([
            'PESQUISA_ID' => $pesquisaId,
            'PERGUNTA_TEXTO' => $p['texto'],
            'PERGUNTA_TIPO' => $p['tipo'],
            'PERGUNTA_OPCOES' => isset($p['opcoes']) ? json_encode($p['opcoes']) : null,
            'PERGUNTA_ORDEM' => $index,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    return response()->json(['ok' => true, 'pesquisa_id' => $pesquisaId]);
})->middleware('perfil:ADMINISTRADOR,Administrador,GESTOR');

Route::get('/pesquisas/{id}/responder', function ($id) {
    ensurePesquisaRhTablesFromRoutes();
    $pesquisa = DB::table('PESQUISA')->where('PESQUISA_ID', $id)->first();
    if (!$pesquisa || !$pesquisa->PESQUISA_ATIVA) {
        return response()->json(['erro' => 'Pesquisa não encontrada ou inativa'], 404);
    }
    $perguntas = DB::table('PESQUISA_PERGUNTA')->where('PESQUISA_ID', $id)->orderBy('PERGUNTA_ORDEM')->get();
    foreach ($perguntas as $p) {
        $p->PERGUNTA_OPCOES = $p->PERGUNTA_OPCOES ? json_decode($p->PERGUNTA_OPCOES) : null;
    }
    return response()->json(['pesquisa' => $pesquisa, 'perguntas' => $perguntas]);
});

Route::post('/pesquisas/{id}/responder', function (Request $request, $id) {
    ensurePesquisaRhTablesFromRoutes();
    if (!\Illuminate\Support\Facades\Schema::hasTable('PESQUISA_RESPOSTA')) {
        return response()->json(['ok' => true, 'fallback' => true]);
    }
    $respostas = $request->input('respostas', []);
    $token = \Illuminate\Support\Str::uuid()->toString();
    
    foreach ($respostas as $perguntaId => $valor) {
        DB::table('PESQUISA_RESPOSTA')->insert([
            'PESQUISA_ID' => $id,
            'PERGUNTA_ID' => $perguntaId,
            'RESPOSTA_VALOR' => is_array($valor) ? json_encode($valor) : $valor,
            'SESSAO_TOKEN' => $token,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
    return response()->json(['ok' => true]);
})->middleware('perfil:SERVIDOR,ADMINISTRADOR,Administrador,GESTOR');

Route::get('/pesquisas/{id}/resultados', function ($id) {
    ensurePesquisaRhTablesFromRoutes();
    $pesquisa = DB::table('PESQUISA')->where('PESQUISA_ID', $id)->first();
    $perguntas = DB::table('PESQUISA_PERGUNTA')->where('PESQUISA_ID', $id)->get();
    
    $resultados = [];
    foreach ($perguntas as $p) {
        $respostas = DB::table('PESQUISA_RESPOSTA')->where('PERGUNTA_ID', $p->PERGUNTA_ID)->pluck('RESPOSTA_VALOR');
        
        $stats = ['total' => count($respostas), 'tipo' => $p->PERGUNTA_TIPO];
        if ($p->PERGUNTA_TIPO === 'NPS') {
            $promotores = 0; $neutros = 0; $detratores = 0;
            foreach ($respostas as $r) {
                $v = (int) $r;
                if ($v >= 9) $promotores++;
                elseif ($v >= 7) $neutros++;
                else $detratores++;
            }
            $total = count($respostas);
            $nps = $total > 0 ? round((($promotores - $detratores) / $total) * 100) : 0;
            $stats['nps'] = $nps;
            $stats['promotores'] = $promotores;
            $stats['neutros'] = $neutros;
            $stats['detratores'] = $detratores;
        } elseif ($p->PERGUNTA_TIPO === 'MULTIPLA') {
            $contagem = [];
            foreach ($respostas as $r) {
                $contagem[$r] = ($contagem[$r] ?? 0) + 1;
            }
            $stats['distribuicao'] = $contagem;
        } else {
            $stats['respostas'] = $respostas;
        }
        $resultados[] = [
            'pergunta' => $p->PERGUNTA_TEXTO,
            'estatisticas' => $stats
        ];
    }
    return response()->json(['pesquisa' => $pesquisa, 'resultados' => $resultados]);
})->middleware('perfil:ADMINISTRADOR,Administrador,GESTOR');

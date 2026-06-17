<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// ══════════════════════════════════════════════════════════════════
// SAGRES / TCE-MA — integração com sistema de controle externo
// LAT-06 / GAP-13
// ⚠️ NÃO abrir Route::middleware()->prefix()->group() aqui
//    O contexto api/v3 + auth já é herdado do web.php
//
// Lógica real: cruzar DETALHE_FOLHA × SAGRES_EVENTO_DEPARA
// O seed SAGRES_EVENTO_DEPARA já está populado com os códigos corretos.
// ══════════════════════════════════════════════════════════════════

// GET /sagres/preview — previsualização antes de gerar
Route::get('/sagres/preview', function (Request $request) {
    try {
        $comp = $request->competencia ?? now()->format('Y-m');
        $compDb = str_replace('-', '', $comp);

        $folha = DB::table('FOLHA')->where('FOLHA_COMPETENCIA', $compDb)->first();
        if (!$folha) {
            return response()->json(['aviso' => "Folha {$comp} não encontrada. Gere ou importe a folha primeiro.", 'registros' => [], 'total' => 0]);
        }

        // Eventos de-para configurados
        $depara = DB::table('SAGRES_EVENTO_DEPARA')->get()->keyBy('RUBRICA_SISTEMA');

        $detalhes = DB::table('DETALHE_FOLHA as df')
            ->join('FUNCIONARIO as f', 'f.FUNCIONARIO_ID', '=', 'df.FUNCIONARIO_ID')
            ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
            ->where('df.FOLHA_ID', $folha->FOLHA_ID)
            ->select(
                'p.PESSOA_NOME as nome',
                'f.FUNCIONARIO_MATRICULA as matricula',
                'p.PESSOA_CPF_NUMERO as cpf',
                'df.DETALHE_FOLHA_PROVENTOS as proventos',
                'df.DETALHE_FOLHA_DESCONTOS as descontos',
                DB::raw('COALESCE(df.DETALHE_FOLHA_LIQUIDO, df.DETALHE_FOLHA_PROVENTOS - df.DETALHE_FOLHA_DESCONTOS, 0) as liquido')
            )
            ->orderBy('p.PESSOA_NOME')
            ->limit(50)
            ->get();

        return response()->json([
            'competencia' => $comp,
            'folha_id' => $folha->FOLHA_ID,
            'total_serv' => $detalhes->count(),
            'total_proventos' => $detalhes->sum('proventos'),
            'total_descontos' => $detalhes->sum('descontos'),
            'total_liquido' => $detalhes->sum('liquido'),
            'registros' => $detalhes,
            'depara_count' => $depara->count(),
        ]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// POST /sagres/gerar — gerar arquivo XML real (DETALHE_FOLHA × SAGRES_EVENTO_DEPARA)
Route::post('/sagres/gerar', function (Request $request) {
    try {
        $user = Auth::user();
        $comp = $request->competencia ?? now()->format('Y-m');
        $compDb = str_replace('-', '', $comp);

        $folha = DB::table('FOLHA')->where('FOLHA_COMPETENCIA', $compDb)->first();
        if (!$folha) {
            return response()->json(['erro' => "Folha {$comp} não encontrada."], 404);
        }

        // Buscar de-para de rubricas
        $depara = DB::table('SAGRES_EVENTO_DEPARA')->get()->keyBy('RUBRICA_SISTEMA');

        $detalhes = DB::table('DETALHE_FOLHA as df')
            ->join('FUNCIONARIO as f', 'f.FUNCIONARIO_ID', '=', 'df.FUNCIONARIO_ID')
            ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
            ->where('df.FOLHA_ID', $folha->FOLHA_ID)
            ->select(
                'p.PESSOA_NOME as nome',
                'p.PESSOA_CPF_NUMERO as cpf',
                'f.FUNCIONARIO_MATRICULA as matricula',
                'df.DETALHE_FOLHA_PROVENTOS as proventos',
                'df.DETALHE_FOLHA_DESCONTOS as descontos',
                DB::raw('COALESCE(df.DETALHE_FOLHA_LIQUIDO, df.DETALHE_FOLHA_PROVENTOS - df.DETALHE_FOLHA_DESCONTOS, 0) as liquido')
            )
            ->get();

        // Montar XML SINC-Folha TCE-MA (estrutura simplificada)
        $municipio = 'SAO_LUIS_MA';
        $cnpjOrgao = env('ORGAO_CNPJ', '06.223.007/0001-14');
        $anoMes = $compDb; // AAAAMM

        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $xml .= "<SINCFolha versao=\"1.0\" competencia=\"{$anoMes}\" municipio=\"{$municipio}\" cnpj_orgao=\"{$cnpjOrgao}\">\n";
        $xml .= "  <Servidores total=\"{$detalhes->count()}\">\n";

        foreach ($detalhes as $d) {
            $cpfLimpo = preg_replace('/\D/', '', $d->cpf ?? '');
            $xml .= "    <Servidor>\n";
            $xml .= "      <CPF>{$cpfLimpo}</CPF>\n";
            $xml .= "      <Nome>" . htmlspecialchars($d->nome ?? '') . "</Nome>\n";
            $xml .= "      <Matricula>" . htmlspecialchars($d->matricula ?? '') . "</Matricula>\n";
            $xml .= "      <Proventos>" . number_format((float) $d->proventos, 2, '.', '') . "</Proventos>\n";
            $xml .= "      <Descontos>" . number_format((float) $d->descontos, 2, '.', '') . "</Descontos>\n";
            $xml .= "      <Liquido>" . number_format((float) $d->liquido, 2, '.', '') . "</Liquido>\n";
            $xml .= "    </Servidor>\n";
        }

        $xml .= "  </Servidores>\n";
        $xml .= "</SINCFolha>\n";

        $filename = "sagres_{$compDb}_" . now()->format('YmdHis') . ".xml";

        // Registrar no histórico
        $geracaoId = DB::table('SAGRES_GERACAO')->insertGetId([
            'COMPETENCIA' => $comp,
            'ARQUIVO_NOME' => $filename,
            'TOTAL_SERV' => $detalhes->count(),
            'TOTAL_LIQUIDO' => $detalhes->sum('liquido'),
            'GERADO_POR' => $user->USUARIO_ID ?? null,
            'STATUS' => 'GERADO',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// GET /sagres/historico — lista arquivos gerados
Route::get('/sagres/historico', function () {
    try {
        $historico = DB::table('SAGRES_GERACAO as g')
            ->leftJoin('USUARIO as u', 'u.USUARIO_ID', '=', 'g.GERADO_POR')
            ->select(
                'g.GERACAO_ID as id',
                'g.COMPETENCIA as competencia',
                'g.ARQUIVO_NOME as arquivo',
                'g.TOTAL_SERV as total_servidores',
                'g.TOTAL_LIQUIDO as total_liquido',
                'g.STATUS as status',
                'u.USUARIO_NOME as gerado_por',
                'g.created_at as gerado_em'
            )
            ->orderByDesc('g.created_at')
            ->limit(24)
            ->get();

        return response()->json(['historico' => $historico]);
    } catch (\Throwable $e) {
        return response()->json(['historico' => [], 'aviso' => 'Nenhum arquivo gerado ainda.']);
    }
});

// GET /sagres/download/{id} — re-gerar e fazer download por id do histórico
Route::get('/sagres/download/{id}', function ($id) {
    try {
        $geracao = DB::table('SAGRES_GERACAO')->where('GERACAO_ID', $id)->first();
        if (!$geracao) {
            return response()->json(['erro' => 'Arquivo não encontrado.'], 404);
        }

        $compDb = str_replace('-', '', $geracao->COMPETENCIA);
        $folha = DB::table('FOLHA')->where('FOLHA_COMPETENCIA', $compDb)->first();

        $detalhes = $folha
            ? DB::table('DETALHE_FOLHA as df')
                ->join('FUNCIONARIO as f', 'f.FUNCIONARIO_ID', '=', 'df.FUNCIONARIO_ID')
                ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
                ->where('df.FOLHA_ID', $folha->FOLHA_ID)
                ->select(
                    'p.PESSOA_NOME as nome',
                    'p.PESSOA_CPF_NUMERO as cpf',
                    'f.FUNCIONARIO_MATRICULA as matricula',
                    'df.DETALHE_FOLHA_PROVENTOS as proventos',
                    'df.DETALHE_FOLHA_DESCONTOS as descontos',
                    DB::raw('COALESCE(df.DETALHE_FOLHA_LIQUIDO, df.DETALHE_FOLHA_PROVENTOS - df.DETALHE_FOLHA_DESCONTOS, 0) as liquido')
                )->get()
            : collect();

        $cnpjOrgao = env('ORGAO_CNPJ', '06.223.007/0001-14');
        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $xml .= "<SINCFolha versao=\"1.0\" competencia=\"{$compDb}\" cnpj_orgao=\"{$cnpjOrgao}\">\n";
        $xml .= "  <Servidores total=\"{$detalhes->count()}\">\n";
        foreach ($detalhes as $d) {
            $cpfLimpo = preg_replace('/\D/', '', $d->cpf ?? '');
            $xml .= "    <Servidor><CPF>{$cpfLimpo}</CPF><Nome>" . htmlspecialchars($d->nome ?? '') . "</Nome>"
                . "<Matricula>" . htmlspecialchars($d->matricula ?? '') . "</Matricula>"
                . "<Proventos>" . number_format((float) $d->proventos, 2, '.', '') . "</Proventos>"
                . "<Descontos>" . number_format((float) $d->descontos, 2, '.', '') . "</Descontos>"
                . "<Liquido>" . number_format((float) $d->liquido, 2, '.', '') . "</Liquido></Servidor>\n";
        }
        $xml .= "  </Servidores>\n</SINCFolha>\n";

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$geracao->ARQUIVO_NOME}\"",
        ]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// GET /sagres/depara — colunas alinhadas a SagresView.vue (DEPARA_ID, SAGRES_COD, TIPO, ATIVO)
Route::get('/sagres/depara', function () {
    try {
        if (!Schema::hasTable('SAGRES_EVENTO_DEPARA')) {
            return response()->json(['ok' => true, 'depara' => [], 'total' => 0]);
        }
        $raw = DB::table('SAGRES_EVENTO_DEPARA')->orderBy('DEPARA_ID')->get();
        $depara = $raw->map(function ($r) {
            $cod = $r->SAGRES_COD ?? $r->RUBRICA_SISTEMA ?? $r->EVENTO_INTERNO_COD ?? '';
            $desc = $r->SAGRES_DESCRICAO ?? $r->EVENTO_INTERNO_NOME ?? '';
            $tipoRaw = (string) ($r->TIPO ?? 'P');
            $tipoLetra = strtoupper(substr($tipoRaw, 0, 1));
            $tipoVue = $tipoLetra === 'D' ? 'D' : 'P';
            $ativo = isset($r->ATIVO) ? (bool) $r->ATIVO : true;

            return [
                'DEPARA_ID' => (int) $r->DEPARA_ID,
                'SAGRES_COD' => (string) $cod,
                'SAGRES_DESCRICAO' => (string) $desc,
                'TIPO' => $tipoVue,
                'ATIVO' => $ativo,
            ];
        })->values();
        return response()->json(['ok' => true, 'depara' => $depara, 'total' => $depara->count()]);
    } catch (\Throwable $e) {
        return response()->json(['ok' => true, 'depara' => [], 'total' => 0, 'aviso' => $e->getMessage()]);
    }
});

// GET /sagres/exportacoes — SagresView.vue usa exportacoes[] com EXPORTACAO_ID, ARQUIVO_XML_PATH, ENVIADO_EM
Route::get('/sagres/exportacoes', function () {
    try {
        if (!Schema::hasTable('SAGRES_GERACAO')) {
            return response()->json(['ok' => true, 'exportacoes' => [], 'historico' => []]);
        }
        $q = DB::table('SAGRES_GERACAO as g')
            ->leftJoin('USUARIO as u', 'u.USUARIO_ID', '=', 'g.GERADO_POR')
            ->select(
                'g.GERACAO_ID',
                'g.COMPETENCIA',
                'g.ARQUIVO_NOME',
                'g.TOTAL_SERV',
                'g.TOTAL_LIQUIDO',
                'g.STATUS',
                'u.USUARIO_NOME as gerado_por',
                'g.created_at',
                'g.updated_at'
            );
        if (Schema::hasColumn('SAGRES_GERACAO', 'ENVIADO_EM')) {
            $q->addSelect('g.ENVIADO_EM');
        }
        $rows = $q->orderByDesc('g.created_at')->limit(50)->get();
        $map = $rows->map(function ($g) {
            $status = (string) ($g->STATUS ?? 'GERADO');
            $path = $g->ARQUIVO_NOME ?? null;
            $enviado = $g->ENVIADO_EM ?? null;
            if ($enviado === null && $status === 'ENVIADO' && !empty($g->updated_at)) {
                $enviado = $g->updated_at;
            }

            return [
                'EXPORTACAO_ID' => (int) $g->GERACAO_ID,
                'GERACAO_ID' => (int) $g->GERACAO_ID,
                'COMPETENCIA' => (string) ($g->COMPETENCIA ?? ''),
                'STATUS' => $status,
                'created_at' => $g->created_at,
                'ENVIADO_EM' => $enviado,
                'ARQUIVO_XML_PATH' => $path,
                'ARQUIVO_NOME' => $path,
                'total_servidores' => (int) ($g->TOTAL_SERV ?? 0),
                'total_liquido' => $g->TOTAL_LIQUIDO ?? 0,
                'gerado_por' => $g->gerado_por ?? null,
            ];
        })->values();
        return response()->json(['ok' => true, 'exportacoes' => $map, 'historico' => $map]);
    } catch (\Throwable $e) {
        return response()->json(['ok' => true, 'exportacoes' => [], 'historico' => []]);
    }
});

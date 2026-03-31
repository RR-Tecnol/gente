<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Lista contratos com filtros
Route::get('/contratos-admin', function () {
    try {
        $q = DB::table('CONTRATO_ADMINISTRATIVO as c')
            ->leftJoin('PROCESSO_LICITATORIO as p', 'p.PROCESSO_ID', '=', 'c.PROCESSO_ID');

        if (request('status'))   $q->where('c.CONTRATO_STATUS', request('status'));
        if (request('busca'))    $q->where('c.CONTRATO_OBJETO', 'like', '%' . request('busca') . '%');

        $contratos = $q->select('c.*', 'p.PROCESSO_NUMERO', 'p.PROCESSO_MODALIDADE')
            ->orderByDesc('c.CONTRATO_INICIO')
            ->limit(300)
            ->get();

        return response()->json(['contratos' => $contratos, 'total' => $contratos->count()]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// Detalhes + histórico de aditivos e fiscalizações
Route::get('/contratos-admin/{id}', function (int $id) {
    try {
        $contrato = DB::table('CONTRATO_ADMINISTRATIVO')->where('CONTRATO_ID', $id)->first();
        if (!$contrato) return response()->json(['erro' => 'Contrato não encontrado.'], 404);

        $aditivos = DB::table('CONTRATO_ADITIVO')
            ->where('CONTRATO_ID', $id)
            ->orderBy('ADITIVO_NUMERO')
            ->get();

        $fiscalizacoes = DB::table('CONTRATO_FISCALIZACAO')
            ->where('CONTRATO_ID', $id)
            ->orderByDesc('FISCAL_DATA')
            ->get();

        return response()->json([
            'contrato'      => $contrato,
            'aditivos'      => $aditivos,
            'fiscalizacoes' => $fiscalizacoes,
        ]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// Contratos vencendo em 60 dias
Route::get('/contratos-admin/vencendo', function () {
    try {
        $hoje   = now()->toDateString();
        $limite = now()->addDays(60)->toDateString();

        $contratos = DB::table('CONTRATO_ADMINISTRATIVO')
            ->where('CONTRATO_STATUS', 'VIGENTE')
            ->where('CONTRATO_FIM', '>=', $hoje)
            ->where('CONTRATO_FIM', '<=', $limite)
            ->orderBy('CONTRATO_FIM')
            ->get()
            ->map(function ($c) use ($hoje) {
                $c->dias_restantes = (int) now()->diffInDays($c->CONTRATO_FIM);
                $c->urgencia = $c->dias_restantes <= 30 ? 'CRITICO' : 'ATENCAO';
                return $c;
            });

        return response()->json(['contratos' => $contratos, 'total' => $contratos->count()]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// Registrar aditivo — atualiza CONTRATO_FIM se houver prorrogação de prazo
Route::post('/contratos-admin/{id}/aditivo', function (int $id) {
    try {
        $contrato = DB::table('CONTRATO_ADMINISTRATIVO')->where('CONTRATO_ID', $id)->first();
        if (!$contrato) return response()->json(['erro' => 'Contrato não encontrado.'], 404);

        $data = request()->validate([
            'aditivo_tipo'       => 'required|string',
            'aditivo_data'       => 'required|date',
            'aditivo_prazo_dias' => 'nullable|integer|min:1',
            'aditivo_valor'      => 'nullable|numeric',
            'aditivo_objeto'     => 'nullable|string',
        ]);

        // Calcular nova data de vencimento se houver prorrogação
        $novaDataFim = null;
        if (!empty($data['aditivo_prazo_dias'])) {
            $novaDataFim = date('Y-m-d', strtotime(
                $contrato->CONTRATO_FIM . ' +' . $data['aditivo_prazo_dias'] . ' days'
            ));
        }

        // Número sequencial do aditivo
        $numAditivo = DB::table('CONTRATO_ADITIVO')
            ->where('CONTRATO_ID', $id)->count() + 1;

        DB::transaction(function () use ($id, $data, $novaDataFim, $numAditivo) {
            DB::table('CONTRATO_ADITIVO')->insert([
                'CONTRATO_ID'       => $id,
                'ADITIVO_NUMERO'    => $numAditivo,
                'ADITIVO_TIPO'      => $data['aditivo_tipo'],
                'ADITIVO_DATA'      => $data['aditivo_data'],
                'ADITIVO_PRAZO_DIAS'=> $data['aditivo_prazo_dias'] ?? null,
                'ADITIVO_VALOR'     => $data['aditivo_valor'] ?? null,
                'CONTRATO_FIM_NOVO' => $novaDataFim,
                'ADITIVO_OBJETO'    => $data['aditivo_objeto'] ?? null,
                'REGISTRADO_POR'    => Auth::id(),
                'created_at'        => now(), 'updated_at' => now(),
            ]);

            // Atualizar data de vencimento do contrato
            if ($novaDataFim) {
                DB::table('CONTRATO_ADMINISTRATIVO')
                    ->where('CONTRATO_ID', $id)
                    ->update(['CONTRATO_FIM' => $novaDataFim, 'updated_at' => now()]);
            }
        });

        return response()->json(['ok' => true, 'aditivo_numero' => $numAditivo, 'nova_data_fim' => $novaDataFim]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 422);
    }
});

// Registrar fiscalização mensal
Route::post('/contratos-admin/{id}/fiscalizar', function (int $id) {
    try {
        $data = request()->validate([
            'fiscal_data'        => 'required|date',
            'fiscal_status'      => 'required|in:REGULAR,IRREGULAR,PENDENCIA,SUSPENSO',
            'fiscal_observacao'  => 'nullable|string',
            'fiscal_responsavel' => 'nullable|string|max:150',
        ]);

        DB::table('CONTRATO_FISCALIZACAO')->insert([
            'CONTRATO_ID'       => $id,
            'FISCAL_DATA'       => $data['fiscal_data'],
            'FISCAL_COMPETENCIA'=> date('m/Y', strtotime($data['fiscal_data'])),
            'FISCAL_STATUS'     => $data['fiscal_status'],
            'FISCAL_OBSERVACAO' => $data['fiscal_observacao'] ?? null,
            'FISCAL_RESPONSAVEL'=> $data['fiscal_responsavel'] ?? null,
            'REGISTRADO_POR'    => Auth::id(),
            'created_at'        => now(), 'updated_at' => now(),
        ]);

        return response()->json(['ok' => true]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 422);
    }
});

// Exportar CSV
Route::get('/contratos-admin/export/csv', function () {
    try {
        $contratos = DB::table('CONTRATO_ADMINISTRATIVO')->get();
        $linhas = ["Número,Fornecedor,Objeto,Valor,Início,Fim,Status"];
        foreach ($contratos as $c) {
            $linhas[] = implode(',', [
                $c->CONTRATO_NUMERO,
                '"' . str_replace('"', '""', $c->CONTRATO_FORNECEDOR ?? '') . '"',
                '"' . str_replace('"', '""', $c->CONTRATO_OBJETO) . '"',
                number_format($c->CONTRATO_VALOR, 2, '.', ''),
                $c->CONTRATO_INICIO,
                $c->CONTRATO_FIM,
                $c->CONTRATO_STATUS,
            ]);
        }
        $csv = "\xEF\xBB\xBF" . implode("\r\n", $linhas); // BOM UTF-8
        return response($csv, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="contratos_' . date('Ymd') . '.csv"',
        ]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

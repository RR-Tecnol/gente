<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// ══════════════════════════════════════════════════════════════════════════════
// MOTOR DE FOLHA — Endpoints de apoio Sprint 3 (Partes 9–11)
// Contexto: api/v3 + auth herdado do web.php — NÃO abrir Route::group aqui
// ══════════════════════════════════════════════════════════════════════════════

// ─── VÍNCULOS ────────────────────────────────────────────────────────────────

// GET /api/v3/vinculos — Lista todos os tipos de vínculo
Route::get('/vinculos', function () {
    try {
        $cols = \Illuminate\Support\Facades\Schema::getColumnListing('VINCULO');
        $query = DB::table('VINCULO')->orderBy('VINCULO_NOME');
        $vinculos = $query->get()->map(function ($v) use ($cols) {
            $row = (array) $v;
            // Normaliza flags boolean para front
            foreach (['VINCULO_FGTS', 'VINCULO_INSS', 'VINCULO_IRRF'] as $flag) {
                if (isset($row[$flag]))
                    $row[$flag] = (bool) $row[$flag];
            }
            return $row;
        });
        return response()->json(['vinculos' => $vinculos]);
    } catch (\Throwable $e) {
        return response()->json(['vinculos' => [], 'erro' => $e->getMessage()], 500);
    }
});
Route::post('/vinculos', function (\Illuminate\Http\Request $request) {
    try {
        $id = \Illuminate\Support\Facades\DB::table('VINCULO')->insertGetId([
            'VINCULO_NOME' => $request->VINCULO_NOME, 'VINCULO_SIGLA' => $request->VINCULO_SIGLA ?? null,
            'VINCULO_DESCRICAO' => $request->VINCULO_DESCRICAO ?? null,
            'VINCULO_TIPO_ESOCIAL' => $request->VINCULO_TIPO_ESOCIAL ?? null,
            'VINCULO_FGTS' => $request->VINCULO_FGTS ? 1 : 0,
            'VINCULO_INSS' => $request->VINCULO_INSS ? 1 : 0,
            'VINCULO_IRRF' => $request->VINCULO_IRRF ? 1 : 0,
            'VINCULO_ATIVO' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        return response()->json(['ok' => true, 'id' => $id], 201);
    } catch (\Throwable $e) { return response()->json(['erro' => $e->getMessage()], 422); }
});
Route::put('/vinculos/{id}', function (int $id, \Illuminate\Http\Request $request) {
    try {
        $data = ['updated_at' => now()];
        foreach (['VINCULO_NOME','VINCULO_SIGLA','VINCULO_DESCRICAO','VINCULO_TIPO_ESOCIAL'] as $col) {
            if ($request->has($col)) $data[$col] = $request->input($col);
        }
        foreach (['VINCULO_FGTS','VINCULO_INSS','VINCULO_IRRF'] as $flag) {
            if ($request->has($flag)) $data[$flag] = $request->input($flag) ? 1 : 0;
        }
        \Illuminate\Support\Facades\DB::table('VINCULO')->where('VINCULO_ID', $id)->update($data);
        return response()->json(['ok' => true]);
    } catch (\Throwable $e) { return response()->json(['erro' => $e->getMessage()], 500); }
});
Route::delete('/vinculos/{id}', function (int $id) {
    try {
        \Illuminate\Support\Facades\DB::table('VINCULO')->where('VINCULO_ID', $id)
            ->update(['VINCULO_ATIVO' => 0, 'updated_at' => now()]);
        return response()->json(['ok' => true]);
    } catch (\Throwable $e) { return response()->json(['erro' => $e->getMessage()], 500); }
});

// PATCH /api/v3/vinculos/{id} — Edição inline dos flags do motor
Route::patch('/vinculos/{id}', function (int $id, Request $request) {
    try {
        $cols = \Illuminate\Support\Facades\Schema::getColumnListing('VINCULO');
        $data = [];
        foreach (['VINCULO_REGIME', 'VINCULO_FGTS', 'VINCULO_INSS', 'VINCULO_IRRF', 'VINCULO_ANUENIO_PCT'] as $col) {
            if (in_array($col, $cols) && $request->has($col)) {
                $val = $request->input($col);
                if (in_array($col, ['VINCULO_FGTS', 'VINCULO_INSS', 'VINCULO_IRRF']))
                    $val = $val ? 1 : 0;
                $data[$col] = $val;
            }
        }
        if (empty($data))
            return response()->json(['ok' => true, 'aviso' => 'Nada para atualizar.']);
        DB::table('VINCULO')->where('VINCULO_ID', $id)->update($data);
        return response()->json(['ok' => true]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// ─── RUBRICAS ────────────────────────────────────────────────────────────────

// GET /api/v3/rubricas — Lista rubricas (opcional: ?camada=1|2|3)
Route::get('/rubricas', function (Request $request) {
    try {
        $cols = \Illuminate\Support\Facades\Schema::getColumnListing('RUBRICA');
        $query = DB::table('RUBRICA')->where('RUBRICA_ATIVO', 1)->orderBy('RUBRICA_CODIGO');
        if ($request->camada && in_array('RUBRICA_CAMADA', $cols)) {
            $query->where('RUBRICA_CAMADA', $request->camada);
        }
        $rubricas = $query->get();
        return response()->json(['rubricas' => $rubricas]);
    } catch (\Throwable $e) {
        return response()->json(['rubricas' => [], 'erro' => $e->getMessage()], 500);
    }
});

// ─── ADICIONAIS DO SERVIDOR ──────────────────────────────────────────────────

// GET /api/v3/funcionarios/{id}/adicionais — Lista adicionais ativos
Route::get('/funcionarios/{id}/adicionais', function (int $id) {
    try {
        $adicionais = DB::table('ADICIONAL_SERVIDOR as ads')
            ->leftJoin('RUBRICA as r', 'r.RUBRICA_ID', '=', 'ads.RUBRICA_ID')
            ->where('ads.FUNCIONARIO_ID', $id)
            ->where(function ($q) {
                $q->whereNull('ads.ADICIONAL_VIGENCIA_FIM')
                    ->orWhere('ads.ADICIONAL_VIGENCIA_FIM', '>=', now()->toDateString());
            })
            ->select([
                'ads.ADICIONAL_ID         as id',
                'ads.FUNCIONARIO_ID       as funcionario_id',
                'ads.RUBRICA_ID           as rubrica_id',
                'r.RUBRICA_DESCRICAO      as rubrica_descricao',
                'ads.ADICIONAL_TIPO       as tipo',
                'ads.ADICIONAL_VALOR      as valor',
                'ads.ADICIONAL_VIGENCIA_INICIO as vigencia_inicio',
                'ads.ADICIONAL_VIGENCIA_FIM   as vigencia_fim',
                'ads.ADICIONAL_ATO_ADM        as ato_adm',
                'ads.ADICIONAL_INCIDE_PREV    as incide_prev',
            ])
            ->orderBy('ads.ADICIONAL_VIGENCIA_INICIO', 'desc')
            ->get();
        return response()->json(['adicionais' => $adicionais]);
    } catch (\Throwable $e) {
        return response()->json(['adicionais' => [], 'erro' => $e->getMessage()], 500);
    }
});

// POST /api/v3/funcionarios/{id}/adicionais — Novo adicional permanente
Route::post('/funcionarios/{id}/adicionais', function (int $id, Request $request) {
    try {
        $data = [
            'FUNCIONARIO_ID' => $id,
            'RUBRICA_ID' => $request->rubrica_id,
            'ADICIONAL_TIPO' => $request->tipo ?? 'fixo',
            'ADICIONAL_VALOR' => $request->valor ?? 0,
            'ADICIONAL_VIGENCIA_INICIO' => $request->vigencia_inicio ?? now()->toDateString(),
            'ADICIONAL_INCIDE_PREV' => $request->incide_prev ?? true,
        ];
        if ($request->ato_adm)
            $data['ADICIONAL_ATO_ADM'] = $request->ato_adm;
        if ($request->vigencia_fim)
            $data['ADICIONAL_VIGENCIA_FIM'] = $request->vigencia_fim;

        // timestamps se existirem
        $cols = \Illuminate\Support\Facades\Schema::getColumnListing('ADICIONAL_SERVIDOR');
        if (in_array('created_at', $cols)) {
            $data['created_at'] = now();
            $data['updated_at'] = now();
        }
        $id_novo = DB::table('ADICIONAL_SERVIDOR')->insertGetId($data);
        return response()->json(['ok' => true, 'id' => $id_novo]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// DELETE /api/v3/funcionarios/{funcId}/adicionais/{adId} — Inativar (set vigencia_fim = hoje)
Route::delete('/funcionarios/{funcId}/adicionais/{adId}', function (int $funcId, int $adId) {
    try {
        $data = ['ADICIONAL_VIGENCIA_FIM' => now()->toDateString()];
        $cols = \Illuminate\Support\Facades\Schema::getColumnListing('ADICIONAL_SERVIDOR');
        if (in_array('updated_at', $cols))
            $data['updated_at'] = now();

        DB::table('ADICIONAL_SERVIDOR')
            ->where('ADICIONAL_ID', $adId)
            ->where('FUNCIONARIO_ID', $funcId)
            ->update($data);
        return response()->json(['ok' => true]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

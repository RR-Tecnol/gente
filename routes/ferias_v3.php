<?php
// FERIAS CRUD - POST/PUT/DELETE /ferias
// Extraido de web.php - herda prefix api/v3 + auth do grupo principal

if (!function_exists('resolveFuncionarioComFallbackDev')) {
    function resolveFuncionarioComFallbackDev($user)
    {
        if (!$user)
            return null;
        return \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID)->first();
    }
}

// ── Saldo de férias — GET /ferias/saldo/{funcionario_id} (FeriasLicencasView: saldo do servidor) ──
if (!function_exists('ferias_v3_dias_uteis_gozo')) {
    function ferias_v3_dias_uteis_gozo($row): int
    {
        $d = (int) ($row->FERIAS_DIAS ?? 0);
        if ($d > 0) {
            return $d;
        }
        if (!empty($row->FERIAS_DATA_INICIO) && !empty($row->FERIAS_DATA_FIM)) {
            $i = strtotime((string) $row->FERIAS_DATA_INICIO);
            $f = strtotime((string) $row->FERIAS_DATA_FIM);
            if ($i && $f && $f >= $i) {
                return (int) round(($f - $i) / 86400);
            }
        }
        return 0;
    }
}

if (!function_exists('ferias_v3_periodos_aquisitivos')) {
    function ferias_v3_periodos_aquisitivos(int $funcionarioId): array
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('FUNCIONARIO') || !\Illuminate\Support\Facades\Schema::hasTable('FERIAS')) {
            return [[
                'periodo' => '—',
                'direito_dias' => 30,
                'usados_dias' => 0,
                'saldo_dias' => 0,
                'vencido' => false,
            ]];
        }
        $func = \Illuminate\Support\Facades\DB::table('FUNCIONARIO')->where('FUNCIONARIO_ID', $funcionarioId)->first();
        if (!$func) {
            return [[
                'periodo' => '—',
                'direito_dias' => 30,
                'usados_dias' => 0,
                'saldo_dias' => 0,
                'vencido' => false,
            ]];
        }
        $feriasRows = \Illuminate\Support\Facades\DB::table('FERIAS')
            ->where('FUNCIONARIO_ID', $funcionarioId)
            ->where(function ($q) {
                $q->whereNull('FERIAS_STATUS')->orWhere('FERIAS_STATUS', '!=', 'CANCELADA');
            })
            ->get();
        $totalUsados = 0;
        foreach ($feriasRows as $fr) {
            $status = strtoupper((string) ($fr->FERIAS_STATUS ?? 'AGENDADA'));
            if ($status === 'CANCELADA') {
                continue;
            }
            $totalUsados += ferias_v3_dias_uteis_gozo($fr);
        }
        $admissao = $func->FUNCIONARIO_DATA_INICIO ?? null;
        if (!$admissao) {
            $direito = 30;
            $usados = min($direito, $totalUsados);
            $saldo = max(0, $direito - $usados);
            return [[
                'periodo' => 'Aquisitivo (sem data admissão)',
                'direito_dias' => $direito,
                'usados_dias' => $usados,
                'saldo_dias' => $saldo,
                'vencido' => false,
            ]];
        }
        $ini = new \DateTime((string) $admissao);
        $hoje = new \DateTime('today');
        $anoIni = (int) $ini->format('Y');
        $rawBlocos = max(0, (int) floor($ini->diff($hoje)->days / 365.25));
        $blocos = min(4, min(8, max(1, $rawBlocos + 1)));
        $periodos = [];
        $restanteUsar = $totalUsados;
        for ($b = 0; $b < $blocos; $b++) {
            $a = $anoIni + $b;
            $direito = 30;
            $usados = min($direito, $restanteUsar);
            $restanteUsar -= $usados;
            $fimAqui = (clone $ini)->modify('+' . (12 * ($b + 1)) . ' month');
            $vencPrazo = (clone $fimAqui)->modify('+12 month');
            $saldoCard = max(0, $direito - $usados);
            $vencido = ($saldoCard > 0 && $hoje > $vencPrazo);
            $periodos[] = [
                'periodo' => $a . '–' . ($a + 1),
                'direito_dias' => $direito,
                'usados_dias' => $usados,
                'saldo_dias' => $saldoCard,
                'vencido' => $vencido,
            ];
        }
        if (!count($periodos)) {
            $periodos[] = [
                'periodo' => (string) $anoIni,
                'direito_dias' => 30,
                'usados_dias' => min(30, $totalUsados),
                'saldo_dias' => max(0, 30 - $totalUsados),
                'vencido' => false,
            ];
        }
        return $periodos;
    }
}

Route::get('/ferias/saldo/{funcionario_id}', function (int $funcionarioId) {
    try {
        $periodosAquisitivos = ferias_v3_periodos_aquisitivos($funcionarioId);
        $totalSaldoDias = (int) array_sum(array_column($periodosAquisitivos, 'saldo_dias'));
        return response()->json([
            'ok' => true,
            'funcionario_id' => $funcionarioId,
            'periodos_aquisitivos' => $periodosAquisitivos,
            'total_saldo_dias' => $totalSaldoDias,
        ]);
    } catch (\Throwable $e) {
        return response()->json([
            'ok' => true,
            'funcionario_id' => $funcionarioId,
            'periodos_aquisitivos' => [],
            'total_saldo_dias' => 0,
            'aviso' => $e->getMessage(),
        ], 200);
    }
});

// ── Anexo de férias — POST /ferias/{ferias_id}/anexo (FeriasLicencasView: pedido específico) ──
Route::post('/ferias/{ferias_id}/anexo', function (int $feriasId, \Illuminate\Http\Request $request) {
    try {
        $ferias = \App\Models\Ferias::find($feriasId);
        if (!$ferias) {
            return response()->json(['erro' => 'Férias não encontradas.'], 404);
        }
        if (!$request->hasFile('anexo') && !$request->hasFile('arquivo') && !$request->hasFile('file')) {
            return response()->json(['erro' => 'Nenhum arquivo enviado (anexo, arquivo ou file).'], 422);
        }
        $file = $request->file('anexo') ?? $request->file('arquivo') ?? $request->file('file');
        $path = $file->store("ferias/{$feriasId}/anexos", 'local');
        return response()->json(['ok' => true, 'path' => $path, 'ferias_id' => $feriasId], 201);
    } catch (\Throwable $e) {
        return response()->json(['erro' => 'Falha no upload: ' . $e->getMessage()], 500);
    }
});

//  Criar agendamento de férias
Route::post('/ferias', function (\Illuminate\Http\Request $request) {
    try {
        $user = \Illuminate\Support\Facades\Auth::user();
        $funcionario = resolveFuncionarioComFallbackDev($user);
        if (!$funcionario) {
            return response()->json(['erro' => 'Funcionário não vinculado ao seu usuário.'], 422);
        }

        $ferias = \App\Models\Ferias::create([
            'FUNCIONARIO_ID' => $funcionario->FUNCIONARIO_ID,
            'FERIAS_DATA_INICIO' => $request->FERIAS_DATA_INICIO,
            'FERIAS_DATA_FIM' => $request->FERIAS_DATA_FIM,
            'FERIAS_AQUISITIVO_INICIO' => $request->FERIAS_AQUISITIVO_INICIO ?? null,
            'FERIAS_AQUISITIVO_FIM' => $request->FERIAS_AQUISITIVO_FIM ?? null,
            'FERIAS_DIAS_PECUNIA' => $request->input('FERIAS_DIAS_PECUNIA', 0),
        ]);

        return response()->json(['message' => 'Férias agendadas com sucesso.', 'ferias_id' => $ferias->FERIAS_ID], 201);
    } catch (\Throwable $e) {
        \Illuminate\Support\Facades\Log::error('Erro ao criar férias: ' . $e->getMessage());
        return response()->json(['erro' => 'Erro ao registrar férias: ' . $e->getMessage()], 500);
    }
});

//  Atualizar período de férias
Route::put('/ferias/{id}', function ($id, \Illuminate\Http\Request $request) {
    try {
        $user = \Illuminate\Support\Facades\Auth::user();
        $funcionario = resolveFuncionarioComFallbackDev($user);
        $ferias = \App\Models\Ferias::find($id);
        if (!$ferias) {
            return response()->json(['erro' => 'Férias não encontradas.'], 404);
        }

        // Verifica se as férias pertencem ao funcionário logado (ou é admin)
        if (!$funcionario || $ferias->FUNCIONARIO_ID !== $funcionario->FUNCIONARIO_ID) {
            return response()->json(['erro' => 'Sem permissão para editar estas férias.'], 403);
        }

        if ($request->has('FERIAS_DATA_INICIO'))
            $ferias->FERIAS_DATA_INICIO = $request->FERIAS_DATA_INICIO;
        if ($request->has('FERIAS_DATA_FIM'))
            $ferias->FERIAS_DATA_FIM = $request->FERIAS_DATA_FIM;
            
        if ($request->has('FERIAS_DIAS_PECUNIA'))
            $ferias->FERIAS_DIAS_PECUNIA = $request->input('FERIAS_DIAS_PECUNIA', 0);
        if ($request->has('FERIAS_AQUISITIVO_INICIO'))
            $ferias->FERIAS_AQUISITIVO_INICIO = $request->FERIAS_AQUISITIVO_INICIO;
        if ($request->has('FERIAS_AQUISITIVO_FIM'))
            $ferias->FERIAS_AQUISITIVO_FIM = $request->FERIAS_AQUISITIVO_FIM;
        if (!$ferias->isDirty()) {
            return response()->json(['erro' => 'Nenhuma alteração informada para as férias.'], 422);
        }
        $ferias->save();

        return response()->json(['message' => 'Férias atualizadas com sucesso.']);
    } catch (\Throwable $e) {
        return response()->json(['erro' => 'Erro ao atualizar: ' . $e->getMessage()], 500);
    }
});

//  Cancelar / excluir férias
Route::delete('/ferias/{id}', function ($id) {
    try {
        $user = \Illuminate\Support\Facades\Auth::user();
        $funcionario = resolveFuncionarioComFallbackDev($user);
        $ferias = \App\Models\Ferias::find($id);
        if (!$ferias) {
            return response()->json(['erro' => 'Férias não encontradas.'], 404);
        }

        if (!$funcionario || $ferias->FUNCIONARIO_ID !== $funcionario->FUNCIONARIO_ID) {
            return response()->json(['erro' => 'Sem permissão para cancelar estas férias.'], 403);
        }

        if (!$ferias->delete()) {
            return response()->json(['erro' => 'Não foi possível cancelar as férias.'], 500);
        }
        return response()->json(['message' => 'Férias canceladas com sucesso.']);
    } catch (\Throwable $e) {
        return response()->json(['erro' => 'Erro ao cancelar: ' . $e->getMessage()], 500);
    }
});

// ── GAP-FER: Calcular prévia de férias ─────────────────────────────────────
Route::get('/ferias/calcular/{funcionario_id}', function (int $funcionarioId) {
    try {
        $dias = (int) request('dias', 30);
        $service = new \App\Services\FeriasService();
        $calc = $service->calcular($funcionarioId, $dias);
        return response()->json($calc);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 422);
    }
});

// ── GAP-FER: Listar férias com status e valores ─────────────────────────────
Route::get('/ferias/admin', function () {
    try {
        $ferias = \Illuminate\Support\Facades\DB::table('FERIAS as fe')
            ->join('FUNCIONARIO as f', 'f.FUNCIONARIO_ID', '=', 'fe.FUNCIONARIO_ID')
            ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
            ->select(
                'fe.*',
                'p.PESSOA_NOME as nome',
                'f.FUNCIONARIO_MATRICULA as matricula'
            )
            ->orderByDesc('fe.FERIAS_DATA_INICIO')
            ->limit(200)
            ->get();
        return response()->json(['ferias' => $ferias]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// ── GAP-FER: Aprovar férias e calcular valores ─────────────────────────────
Route::post('/ferias/{id}/aprovar', function (int $id) {
    try {
        $user = \Illuminate\Support\Facades\Auth::user();
        $competencia = request('competencia'); // AAAAMM
        if (!$competencia) {
            return response()->json(['erro' => 'competencia é obrigatório (AAAAMM).'], 422);
        }
        $service = new \App\Services\FeriasService();
        $calc = $service->aprovar($id, $user->USUARIO_ID, $competencia);
        return response()->json(['ok' => true, 'calculo' => $calc]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 422);
    }
});

// ═════════════════════════════════════════════════════════════════════
// GAP-ALERT (Fase 4 T4.8 — 08/05/2026): Migração da rota legada
// /ferias/alerta-vencer (web.php, bloco autenticado) → /api/v3/ferias/alerta-vencer
// Lógica idêntica delegada ao FeriasController::alertaVencer (já Auth-aware).
// Filtro: COORD_DE_SETOR vê apenas seu setor; demais perfis veem todos.
// ═════════════════════════════════════════════════════════════════════
Route::get('/ferias/alerta-vencer', [\App\Http\Controllers\FeriasController::class, 'alertaVencer'])
    ->name('api.v3.ferias.alerta-vencer');


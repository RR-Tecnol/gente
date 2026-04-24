<?php
// PLANTOES EXTRAS SOBREAVISO
// Extraido de web.php - herda prefix api/v3 + auth do grupo principal

if (!function_exists('resolveFuncionarioComFallbackDev')) {
    function resolveFuncionarioComFallbackDev($user)
    {
        if (!$user)
            return null;
        $func = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID)->first();
        if ($func)
            return $func;

        if (!app()->isProduction() && strtolower((string) ($user->USUARIO_LOGIN ?? '')) === 'admin') {
            $livre = \App\Models\Funcionario::whereNull('USUARIO_ID')->orderBy('FUNCIONARIO_ID')->first();
            if ($livre) {
                \Illuminate\Support\Facades\DB::table('FUNCIONARIO')
                    ->where('FUNCIONARIO_ID', $livre->FUNCIONARIO_ID)
                    ->update(['USUARIO_ID' => $user->USUARIO_ID]);
                return \App\Models\Funcionario::where('FUNCIONARIO_ID', $livre->FUNCIONARIO_ID)->first();
            }
        }

        return null;
    }
}

//  Banco de Horas: apurações mensais
Route::get('/banco-horas', function (\Illuminate\Http\Request $request) {
    try {
        $user = \Illuminate\Support\Facades\Auth::user();
        $funcionario = resolveFuncionarioComFallbackDev($user);
        if (!$funcionario)
            return response()->json(['apuracoes' => [], 'fallback' => true]);

        $apuracoes = \App\Models\ApuracaoPonto::where('FUNCIONARIO_ID', $funcionario->FUNCIONARIO_ID)
            ->orderByDesc('APURACAO_COMPETENCIA')
            ->take(12)
            ->get()
            ->map(fn($a) => [
                'competencia' => $a->APURACAO_COMPETENCIA,
                'horas_trab' => round($a->APURACAO_HORAS_TRAB ?? 0, 1),
                'horas_extra' => round($a->APURACAO_HORAS_EXTRA ?? 0, 1),
                'horas_falta' => round($a->APURACAO_HORAS_FALTA ?? 0, 1),
                'status' => $a->APURACAO_STATUS,
            ]);

        $saldoAcum = 0;
        foreach ($apuracoes as &$a) {
            $saldoAcum += ($a['horas_extra'] - $a['horas_falta']);
            $a['saldo_acumulado'] = round($saldoAcum, 1);
        }

        return response()->json(['apuracoes' => $apuracoes]);
    } catch (\Throwable $e) {
        return response()->json(['apuracoes' => [], 'fallback' => true, 'erro' => $e->getMessage()]);
    }
});

//  Plantões Extras: listar
Route::get('/plantoes-extras', function (\Illuminate\Http\Request $request) {
    try {
        $user = \Illuminate\Support\Facades\Auth::user();
        $funcionario = resolveFuncionarioComFallbackDev($user);
        if (!$funcionario)
            return response()->json(['plantoes' => [], 'fallback' => true]);

        $cols = \Illuminate\Support\Facades\Schema::hasTable('PLANTAO_EXTRA')
            ? \Illuminate\Support\Facades\Schema::getColumnListing('PLANTAO_EXTRA')
            : [];
        if (empty($cols)) {
            return response()->json(['plantoes' => [], 'fallback' => true]);
        }

        $colData = in_array('PLANTAO_DATA', $cols, true) ? 'PLANTAO_DATA' : (in_array('DATA_PLANTAO', $cols, true) ? 'DATA_PLANTAO' : 'PLANTAO_ID');
        $plantoes = \Illuminate\Support\Facades\DB::table('PLANTAO_EXTRA')
            ->where('FUNCIONARIO_ID', $funcionario->FUNCIONARIO_ID)
            ->orderByDesc($colData)
            ->get()
            ->map(function ($p) {
                $arr = (array) $p;
                $arr['PLANTAO_DATA'] = $arr['PLANTAO_DATA'] ?? ($arr['DATA_PLANTAO'] ?? null);
                $arr['PLANTAO_HORAS'] = $arr['PLANTAO_HORAS'] ?? ($arr['TOTAL_HORAS'] ?? null);
                $arr['PLANTAO_STATUS'] = $arr['PLANTAO_STATUS'] ?? ($arr['STATUS'] ?? 'PENDENTE');
                return $arr;
            });

        return response()->json(['plantoes' => $plantoes]);
    } catch (\Throwable $e) {
        return response()->json(['plantoes' => [], 'fallback' => true]);
    }
});

//  Plantões Extras: solicitar
Route::post('/plantoes-extras', function (\Illuminate\Http\Request $request) {
    try {
        $user = \Illuminate\Support\Facades\Auth::user();
        $funcionario = resolveFuncionarioComFallbackDev($user);
        if (!$funcionario)
            return response()->json(['erro' => 'Funcionário não encontrado.'], 404);

        if (!\Illuminate\Support\Facades\Schema::hasTable('PLANTAO_EXTRA')) {
            return response()->json(['erro' => 'Tabela PLANTAO_EXTRA não encontrada.'], 500);
        }
        $cols = \Illuminate\Support\Facades\Schema::getColumnListing('PLANTAO_EXTRA');
        $horaIni = $request->horaIni ?? $request->hora_ini;
        $horaFim = $request->horaFim ?? $request->hora_fim;
        $minDuracao = 0;
        if ($horaIni && $horaFim) {
            [$h1, $m1] = array_map('intval', explode(':', $horaIni));
            [$h2, $m2] = array_map('intval', explode(':', $horaFim));
            $minDuracao = max(0, (($h2 * 60 + $m2) - ($h1 * 60 + $m1)));
        }
        $horasDuracao = $minDuracao > 0 ? round($minDuracao / 60, 2) : null;

        $payload = [];
        if (in_array('FUNCIONARIO_ID', $cols, true))
            $payload['FUNCIONARIO_ID'] = $funcionario->FUNCIONARIO_ID;
        if (in_array('PLANTAO_DATA', $cols, true))
            $payload['PLANTAO_DATA'] = $request->data;
        if (in_array('DATA_PLANTAO', $cols, true))
            $payload['DATA_PLANTAO'] = $request->data;
        if (in_array('PLANTAO_HORA_INI', $cols, true))
            $payload['PLANTAO_HORA_INI'] = $horaIni;
        if (in_array('PLANTAO_HORA_FIM', $cols, true))
            $payload['PLANTAO_HORA_FIM'] = $horaFim;
        if (in_array('PLANTAO_HORAS', $cols, true))
            $payload['PLANTAO_HORAS'] = $horasDuracao;
        if (in_array('TOTAL_HORAS', $cols, true))
            $payload['TOTAL_HORAS'] = $horasDuracao;
        if (in_array('PLANTAO_SETOR', $cols, true))
            $payload['PLANTAO_SETOR'] = $request->setor;
        if (in_array('PLANTAO_TIPO', $cols, true))
            $payload['PLANTAO_TIPO'] = $request->tipo ?? 'programado';
        if (in_array('PLANTAO_TURNO', $cols, true))
            $payload['PLANTAO_TURNO'] = $request->turno ?? (($request->tipo ?? '') === 'urgencia' ? 'U' : 'D');
        if (in_array('PLANTAO_JUST', $cols, true))
            $payload['PLANTAO_JUST'] = $request->justificativa;
        if (in_array('PLANTAO_JUSTIFICATIVA', $cols, true))
            $payload['PLANTAO_JUSTIFICATIVA'] = $request->justificativa;
        if (in_array('PLANTAO_MOTIVO', $cols, true))
            $payload['PLANTAO_MOTIVO'] = $request->justificativa;
        if (in_array('PLANTAO_STATUS', $cols, true))
            $payload['PLANTAO_STATUS'] = 'PENDENTE';
        if (in_array('STATUS', $cols, true))
            $payload['STATUS'] = 'PENDENTE';
        if (in_array('created_at', $cols, true))
            $payload['created_at'] = now();
        if (in_array('updated_at', $cols, true))
            $payload['updated_at'] = now();

        $id = \Illuminate\Support\Facades\DB::table('PLANTAO_EXTRA')->insertGetId($payload);
        return response()->json(['message' => 'Solicitação enviada!', 'id' => $id], 201);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

//  Sobreaviso: listar períodos e acionamentos
Route::get('/sobreaviso', function (\Illuminate\Http\Request $request) {
    try {
        $user = \Illuminate\Support\Facades\Auth::user();
        $funcionario = resolveFuncionarioComFallbackDev($user);
        if (!$funcionario) {
            return response()->json(['sobreaviso' => [], 'acionamentos' => [], 'fallback' => true]);
        }

        $comp = (string) ($request->competencia ?? now()->format('Y-m'));
        if (!preg_match('/^\d{4}-\d{2}$/', $comp)) {
            return response()->json(['erro' => 'Competência inválida. Use YYYY-MM.'], 422);
        }
        [$ano, $mes] = explode('-', $comp);
        $inicio = sprintf('%04d-%02d-01', (int) $ano, (int) $mes);
        $fim = \Carbon\Carbon::createFromDate((int) $ano, (int) $mes, 1)->endOfMonth()->toDateString();

        $sobreaviso = collect();
        if (\Illuminate\Support\Facades\Schema::hasTable('SOBREAVISO')) {
            $sobreaviso = \Illuminate\Support\Facades\DB::table('SOBREAVISO as sb')
                ->leftJoin('SETOR as st', 'st.SETOR_ID', '=', 'sb.SETOR_ID')
                ->where('sb.FUNCIONARIO_ID', $funcionario->FUNCIONARIO_ID)
                ->where(function ($q) use ($inicio, $fim) {
                    $q->whereBetween('sb.SOBREAVISO_INICIO', [$inicio, $fim])
                        ->orWhereBetween('sb.SOBREAVISO_FIM', [$inicio, $fim]);
                })
                ->select('sb.*', 'st.SETOR_NOME')
                ->get()
                ->map(function ($r) {
                    $arr = (array) $r;
                    $arr['SOBREAVISO_SETOR'] = $arr['SOBREAVISO_SETOR'] ?? ($arr['SETOR_NOME'] ?? null);
                    return $arr;
                });
        } elseif (\Illuminate\Support\Facades\Schema::hasTable('ESCALA_SOBREAVISO')) {
            $sobreaviso = \Illuminate\Support\Facades\DB::table('ESCALA_SOBREAVISO as es')
                ->leftJoin('SETOR as st', 'st.SETOR_ID', '=', 'es.SETOR_ID')
                ->where('es.FUNCIONARIO_ID', $funcionario->FUNCIONARIO_ID)
                ->whereBetween('es.SOBREAVISO_DATA', [$inicio, $fim])
                ->orderBy('es.SOBREAVISO_DATA')
                ->select('es.*', 'st.SETOR_NOME')
                ->get()
                ->map(function ($r) {
                    return [
                        'SOBREAVISO_ID' => $r->SOBREAVISO_ID,
                        'FUNCIONARIO_ID' => $r->FUNCIONARIO_ID,
                        'SOBREAVISO_INICIO' => $r->SOBREAVISO_DATA,
                        'SOBREAVISO_FIM' => $r->SOBREAVISO_DATA,
                        'SOBREAVISO_DATA' => $r->SOBREAVISO_DATA,
                        'SOBREAVISO_TURNO' => $r->SOBREAVISO_TURNO ?? null,
                        'SOBREAVISO_HORAS' => $r->SOBREAVISO_HORAS ?? 0,
                        'SOBREAVISO_SETOR' => $r->SETOR_NOME ?? '—',
                        'SOBREAVISO_ATIVO' => \Carbon\Carbon::parse($r->SOBREAVISO_DATA)->isFuture() ? 1 : 0,
                        'SOBREAVISO_PERCENTUAL' => null,
                        'SOBREAVISO_VALOR' => null,
                    ];
                });
        }

        $acionamentos = collect();
        if (\Illuminate\Support\Facades\Schema::hasTable('ACIONAMENTO')) {
            $colsAc = \Illuminate\Support\Facades\Schema::getColumnListing('ACIONAMENTO');
            $acionamentos = \Illuminate\Support\Facades\DB::table('ACIONAMENTO')
                ->where('FUNCIONARIO_ID', $funcionario->FUNCIONARIO_ID)
                ->whereBetween('ACIONAMENTO_DATA', [$inicio, $fim])
                ->orderByDesc('ACIONAMENTO_DATA')
                ->get()
                ->map(function ($r) use ($colsAc) {
                    $ini = in_array('ACIONAMENTO_HORA_INI', $colsAc, true) ? (string) ($r->ACIONAMENTO_HORA_INI ?? '') : '';
                    $fimHora = in_array('ACIONAMENTO_HORA_FIM', $colsAc, true) ? (string) ($r->ACIONAMENTO_HORA_FIM ?? '') : '';
                    $duracao = 0.0;
                    if (str_contains($ini, ':') && str_contains($fimHora, ':')) {
                        [$h1, $m1] = array_map('intval', explode(':', $ini));
                        [$h2, $m2] = array_map('intval', explode(':', $fimHora));
                        $minIni = ($h1 * 60) + $m1;
                        $minFim = ($h2 * 60) + $m2;
                        if ($minFim < $minIni) {
                            $minFim += 24 * 60;
                        }
                        $duracao = round(max(0, $minFim - $minIni) / 60, 2);
                    }
                    return [
                        'ACIONAMENTO_ID' => $r->ACIONAMENTO_ID ?? null,
                        'FUNCIONARIO_ID' => $r->FUNCIONARIO_ID ?? null,
                        'ACIONAMENTO_DATA' => $r->ACIONAMENTO_DATA ?? null,
                        'ACIONAMENTO_MOTIVO' => $r->ACIONAMENTO_MOTIVO ?? null,
                        'ACIONAMENTO_LOCAL' => $r->ACIONAMENTO_LOCAL ?? null,
                        'ACIONAMENTO_HORA_INI' => $ini,
                        'ACIONAMENTO_HORA_FIM' => $fimHora,
                        'ACIONAMENTO_DURACAO' => $duracao,
                        'ACIONAMENTO_VALOR' => null,
                        'ACIONAMENTO_PAGO' => 0,
                    ];
                });
        } elseif (\Illuminate\Support\Facades\Schema::hasTable('ACIONAMENTO_SOBREAVISO')) {
            $colsAlt = \Illuminate\Support\Facades\Schema::getColumnListing('ACIONAMENTO_SOBREAVISO');
            $acionamentos = \Illuminate\Support\Facades\DB::table('ACIONAMENTO_SOBREAVISO')
                ->where('FUNCIONARIO_ID', $funcionario->FUNCIONARIO_ID)
                ->whereBetween('ACIONAMENTO_DATA', [$inicio, $fim])
                ->orderByDesc('ACIONAMENTO_DATA')
                ->get()
                ->map(function ($r) use ($colsAlt) {
                    $ini = (string) ($r->ACIONAMENTO_HORA_INI ?? '');
                    $fimHora = (string) ($r->ACIONAMENTO_HORA_FIM ?? '');
                    if (($ini === '' || $fimHora === '') && in_array('ACIONAMENTO_HORA', $colsAlt, true)) {
                        $hora = (string) ($r->ACIONAMENTO_HORA ?? '');
                        $parts = array_map('trim', explode('-', $hora));
                        $ini = $ini !== '' ? $ini : ($parts[0] ?? '');
                        $fimHora = $fimHora !== '' ? $fimHora : ($parts[1] ?? '');
                    }
                    return [
                        'ACIONAMENTO_ID' => $r->ACIONAMENTO_ID ?? null,
                        'FUNCIONARIO_ID' => $r->FUNCIONARIO_ID ?? null,
                        'ACIONAMENTO_DATA' => $r->ACIONAMENTO_DATA ?? null,
                        'ACIONAMENTO_MOTIVO' => $r->ACIONAMENTO_MOTIVO ?? null,
                        'ACIONAMENTO_LOCAL' => $r->ACIONAMENTO_LOCAL ?? null,
                        'ACIONAMENTO_HORA_INI' => $ini,
                        'ACIONAMENTO_HORA_FIM' => $fimHora,
                        'ACIONAMENTO_DURACAO' => $r->ACIONAMENTO_DURACAO ?? 0,
                        'ACIONAMENTO_VALOR' => $r->ACIONAMENTO_VALOR ?? null,
                        'ACIONAMENTO_PAGO' => $r->ACIONAMENTO_PAGO ?? 0,
                    ];
                });
        }

        return response()->json([
            'sobreaviso' => $sobreaviso->values(),
            'acionamentos' => $acionamentos->values(),
            'fallback' => $sobreaviso->isEmpty() && $acionamentos->isEmpty(),
        ]);
    } catch (\Throwable $e) {
        return response()->json(['sobreaviso' => [], 'acionamentos' => [], 'fallback' => true, 'erro' => $e->getMessage()], 500);
    }
});

//  Sobreaviso: registrar acionamento
Route::post('/sobreaviso/acionamento', function (\Illuminate\Http\Request $request) {
    try {
        $user = \Illuminate\Support\Facades\Auth::user();
        $funcionario = resolveFuncionarioComFallbackDev($user);
        if (!$funcionario) {
            return response()->json(['erro' => 'Funcionário não encontrado.'], 404);
        }

        $horaIni = (string) ($request->horaIni ?? $request->hora_ini ?? '');
        $horaFim = (string) ($request->horaFim ?? $request->hora_fim ?? '');
        $dataAcion = (string) ($request->data ?? now()->toDateString());
        if ($dataAcion === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataAcion)) {
            return response()->json(['erro' => 'Data inválida. Use YYYY-MM-DD.'], 422);
        }
        if (trim((string) $request->motivo) === '') {
            return response()->json(['erro' => 'Motivo é obrigatório.'], 422);
        }
        $duracao = 0.0;
        if ($horaIni !== '' && $horaFim !== '' && str_contains($horaIni, ':') && str_contains($horaFim, ':')) {
            [$h1, $m1] = array_map('intval', explode(':', $horaIni));
            [$h2, $m2] = array_map('intval', explode(':', $horaFim));
            $minIni = ($h1 * 60) + $m1;
            $minFim = ($h2 * 60) + $m2;
            if ($minFim < $minIni) {
                $minFim += 24 * 60;
            }
            $duracao = round(max(0, $minFim - $minIni) / 60, 2);
        }
        $valorAcionamento = round($duracao * 74.0, 2); // valor hora padrão até regra oficial por vínculo

        if (!\Illuminate\Support\Facades\Schema::hasTable('ACIONAMENTO') && !\Illuminate\Support\Facades\Schema::hasTable('ACIONAMENTO_SOBREAVISO')) {
            return response()->json(['erro' => 'Tabela de acionamentos não encontrada.'], 500);
        }
        $table = \Illuminate\Support\Facades\Schema::hasTable('ACIONAMENTO') ? 'ACIONAMENTO' : 'ACIONAMENTO_SOBREAVISO';
        $cols = \Illuminate\Support\Facades\Schema::getColumnListing($table);
        $payload = [];
        if (in_array('FUNCIONARIO_ID', $cols, true)) $payload['FUNCIONARIO_ID'] = $funcionario->FUNCIONARIO_ID;
        if (in_array('ACIONAMENTO_DATA', $cols, true)) $payload['ACIONAMENTO_DATA'] = $dataAcion;
        if (in_array('ACIONAMENTO_LOCAL', $cols, true)) $payload['ACIONAMENTO_LOCAL'] = $request->local;
        if (in_array('ACIONAMENTO_HORA_INI', $cols, true)) $payload['ACIONAMENTO_HORA_INI'] = $horaIni;
        if (in_array('ACIONAMENTO_HORA_FIM', $cols, true)) $payload['ACIONAMENTO_HORA_FIM'] = $horaFim;
        if (in_array('ACIONAMENTO_HORA', $cols, true) && !in_array('ACIONAMENTO_HORA_INI', $cols, true)) {
            $payload['ACIONAMENTO_HORA'] = trim(($horaIni ?: '--:--') . ' - ' . ($horaFim ?: '--:--'));
        }
        if (in_array('ACIONAMENTO_MOTIVO', $cols, true)) $payload['ACIONAMENTO_MOTIVO'] = $request->motivo;
        if (in_array('ACIONAMENTO_STATUS', $cols, true)) $payload['ACIONAMENTO_STATUS'] = 'PENDENTE';
        if (in_array('ACIONAMENTO_DURACAO', $cols, true)) $payload['ACIONAMENTO_DURACAO'] = $duracao;
        if (in_array('ACIONAMENTO_VALOR', $cols, true)) $payload['ACIONAMENTO_VALOR'] = $valorAcionamento;
        if (in_array('ACIONAMENTO_PAGO', $cols, true)) $payload['ACIONAMENTO_PAGO'] = 0;
        if (in_array('created_at', $cols, true)) $payload['created_at'] = now();
        if (in_array('updated_at', $cols, true)) $payload['updated_at'] = now();

        $id = \Illuminate\Support\Facades\DB::table($table)->insertGetId($payload);
        return response()->json([
            'message' => 'Acionamento registrado.',
            'id' => $id,
            'acionamento' => [
                'ACIONAMENTO_ID' => $id,
                'ACIONAMENTO_DATA' => $dataAcion,
                'ACIONAMENTO_LOCAL' => $request->local,
                'ACIONAMENTO_HORA_INI' => $horaIni,
                'ACIONAMENTO_HORA_FIM' => $horaFim,
                'ACIONAMENTO_MOTIVO' => $request->motivo,
                'ACIONAMENTO_DURACAO' => $duracao,
                'ACIONAMENTO_VALOR' => $valorAcionamento,
                'ACIONAMENTO_PAGO' => 0,
            ],
        ], 201);
    } catch (\Throwable $e) {
        return response()->json(['erro' => 'Erro ao registrar acionamento: ' . $e->getMessage()], 500);
    }
});

// ── E2: Escala do servidor logado ──────────────────────────────────────────
Route::get('/escala/minha', function () {
    try {
        $user       = \Illuminate\Support\Facades\Auth::user();
        $funcionario = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID)->first();
        if (!$funcionario) return response()->json(['escala' => [], 'fallback' => true]);

        $competencia = request('competencia') ?? now()->format('m/Y'); // MM/YYYY

        $itens = \Illuminate\Support\Facades\DB::table('ESCALA as e')
            ->join('DETALHE_ESCALA as de', 'de.ESCALA_ID', '=', 'e.ESCALA_ID')
            ->join('DETALHE_ESCALA_ITEM as dei', 'dei.DETALHE_ESCALA_ID', '=', 'de.DETALHE_ESCALA_ID')
            ->where('e.ESCALA_COMPETENCIA', $competencia)
            ->where('de.FUNCIONARIO_ID', $funcionario->FUNCIONARIO_ID)
            ->select(
                'dei.DETALHE_ESCALA_ITEM_DATA as data',
                'dei.TURNO_SIGLA as turno',
                'dei.DETALHE_ESCALA_ITEM_OBS as obs',
                'de.DETALHE_ESCALA_CARGO as cargo'
            )
            ->orderBy('dei.DETALHE_ESCALA_ITEM_DATA')
            ->get();

        return response()->json([
            'competencia' => $competencia,
            'escala'      => $itens,
            'total'       => $itens->count(),
        ]);
    } catch (\Throwable $e) {
        return response()->json(['escala' => [], 'erro' => $e->getMessage()], 500);
    }
});

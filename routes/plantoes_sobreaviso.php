<?php
// PLANTOES EXTRAS SOBREAVISO
// Extraido de web.php - herda prefix api/v3 + auth do grupo principal

if (!function_exists('resolveFuncionarioComFallbackDev')) {
    function resolveFuncionarioComFallbackDev($user)
    {
        if (!$user)
            return null;
        return \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID)->first();
    }
}

//  Plantões Extras: listar (normaliza DATA_PLANTAO, HORA_INICIO, PLANTAO_EXTRA_ID, SETOR, HORA_EXTRA)
Route::get('/plantoes-extras', function () {
    try {
        $user = \Illuminate\Support\Facades\Auth::user();
        $funcionario = resolveFuncionarioComFallbackDev($user);
        if (! $funcionario) {
            return response()->json(['fallback' => true, 'plantoes' => []]);
        }

        $cols = \Illuminate\Support\Facades\Schema::hasTable('PLANTAO_EXTRA')
            ? \Illuminate\Support\Facades\Schema::getColumnListing('PLANTAO_EXTRA')
            : [];
        if (empty($cols)) {
            return response()->json(['fallback' => true, 'plantoes' => []]);
        }

        $colData = in_array('PLANTAO_DATA', $cols, true) ? 'PLANTAO_DATA' : (in_array('DATA_PLANTAO', $cols, true) ? 'DATA_PLANTAO' : (in_array('PLANTAO_ID', $cols, true) ? 'PLANTAO_ID' : 'PLANTAO_EXTRA_ID'));

        $fmtH = static function ($v) {
            if ($v === null || $v === '') {
                return null;
            }
            $s = trim((string) $v);
            if ($s === '') {
                return null;
            }
            if (strlen($s) >= 5 && $s[2] === ':') {
                return substr($s, 0, 5);
            }

            return $s;
        };

        $rawRows = \Illuminate\Support\Facades\DB::table('PLANTAO_EXTRA')
            ->where('FUNCIONARIO_ID', $funcionario->FUNCIONARIO_ID)
            ->orderByDesc($colData)
            ->take(50)
            ->get();

        $rows = $rawRows->map(function ($p) use ($funcionario, $fmtH) {
            $a = (array) $p;
            $id = $a['PLANTAO_ID'] ?? $a['PLANTAO_EXTRA_ID'] ?? null;
            if ($id === null) {
                return $a;
            }
            $data = $a['PLANTAO_DATA'] ?? $a['DATA_PLANTAO'] ?? null;
            if (is_string($data) && strlen($data) > 10) {
                $data = substr($data, 0, 10);
            }
            $hIni = $a['PLANTAO_HORA_INI'] ?? $a['HORA_INICIO'] ?? null;
            $hFim = $a['PLANTAO_HORA_FIM'] ?? $a['HORA_FIM'] ?? null;
            $hIni = $fmtH($hIni);
            $hFim = $fmtH($hFim);
            $horas = $a['PLANTAO_HORAS'] ?? $a['TOTAL_HORAS'] ?? null;
            $setorTxt = $a['PLANTAO_SETOR'] ?? null;
            if (($setorTxt === null || $setorTxt === '') && ! empty($a['SETOR_ID']) && \Illuminate\Support\Facades\Schema::hasTable('SETOR')) {
                $setorTxt = \Illuminate\Support\Facades\DB::table('SETOR')->where('SETOR_ID', $a['SETOR_ID'])->value('SETOR_NOME');
            }
            $tipo = $a['PLANTAO_TIPO'] ?? null;
            if ($tipo === null) {
                $t = (string) ($a['PLANTAO_TURNO'] ?? '');
                $tipo = (strtoupper($t) === 'U') ? 'urgencia' : 'programado';
            } else {
                $tipo = strtolower((string) $tipo);
            }
            $st = $a['PLANTAO_STATUS'] ?? $a['STATUS'] ?? 'PENDENTE';

            $he = null;
            if (\Illuminate\Support\Facades\Schema::hasTable('HORA_EXTRA')) {
                $he = \Illuminate\Support\Facades\DB::table('HORA_EXTRA')
                    ->where('FUNCIONARIO_ID', $funcionario->FUNCIONARIO_ID)
                    ->where('OBSERVACAO', 'like', 'ORIGEM_PLANTAO_EXTRA:' . (int) $id . '%')
                    ->orderByDesc('HORA_EXTRA_ID')
                    ->first();
            }
            if ($hIni === null && $he && ! empty($he->HORA_INICIO)) {
                $hIni = $fmtH($he->HORA_INICIO);
            }
            if ($hFim === null && $he && ! empty($he->HORA_FIM)) {
                $hFim = $fmtH($he->HORA_FIM);
            }
            $valor = isset($a['VALOR_CALCULADO']) ? (float) $a['VALOR_CALCULADO'] : 0.0;
            if ($he && property_exists($he, 'VALOR_CALCULADO') && (float) $he->VALOR_CALCULADO > 0) {
                $valor = (float) $he->VALOR_CALCULADO;
            }

            return array_merge($a, [
                'PLANTAO_ID' => (int) $id,
                'PLANTAO_DATA' => $data,
                'PLANTAO_HORA_INI' => $hIni,
                'PLANTAO_HORA_FIM' => $hFim,
                'PLANTAO_HORAS' => $horas,
                'PLANTAO_SETOR' => $setorTxt ? (string) $setorTxt : null,
                'PLANTAO_TIPO' => $tipo,
                'PLANTAO_STATUS' => is_string($st) ? $st : (string) $st,
                'HORA_EXTRA_ID' => $he->HORA_EXTRA_ID ?? null,
                'HORA_EXTRA_STATUS' => $he->STATUS ?? null,
                'VALOR_CALCULADO' => $valor,
                'PLANTAO_VALOR' => $valor,
            ]);
        });

        return response()->json(['plantoes' => $rows->values(), 'fallback' => $rows->isEmpty()]);
    } catch (\Throwable $e) {
        return response()->json(['fallback' => true, 'plantoes' => [], 'erro' => $e->getMessage()]);
    }
});

Route::post('/plantoes-extras', function (\Illuminate\Http\Request $request) {
    try {
        $user = \Illuminate\Support\Facades\Auth::user();
        $funcionario = resolveFuncionarioComFallbackDev($user);
        if (!$funcionario) {
            return response()->json(['erro' => 'Funcionário não encontrado.'], 404);
        }
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
            $iniMin = ($h1 * 60 + $m1);
            $fimMin = ($h2 * 60 + $m2);
            if ($fimMin < $iniMin) {
                $fimMin += 24 * 60;
            }
            $minDuracao = max(0, $fimMin - $iniMin);
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
        if (in_array('HORA_INICIO', $cols, true))
            $payload['HORA_INICIO'] = $horaIni;
        if (in_array('HORA_FIM', $cols, true))
            $payload['HORA_FIM'] = $horaFim;
        if (in_array('PLANTAO_HORAS', $cols, true))
            $payload['PLANTAO_HORAS'] = $horasDuracao;
        if (in_array('TOTAL_HORAS', $cols, true))
            $payload['TOTAL_HORAS'] = $horasDuracao;
        if (in_array('COMPETENCIA', $cols, true) && $request->data) {
            $payload['COMPETENCIA'] = substr((string) $request->data, 0, 7);
        }
        if (in_array('PLANTAO_SETOR', $cols, true))
            $payload['PLANTAO_SETOR'] = $request->setor;
        if (in_array('SETOR_ID', $cols, true)) {
            $setorNome = trim((string) $request->setor);
            $setorId = null;
            if ($setorNome !== '' && \Illuminate\Support\Facades\Schema::hasTable('SETOR')) {
                $setorId = \Illuminate\Support\Facades\DB::table('SETOR')
                    ->where('SETOR_NOME', $setorNome)
                    ->orWhere('SETOR_SIGLA', $setorNome)
                    ->value('SETOR_ID');
            }
            $payload['SETOR_ID'] = $setorId;
        }
        if (in_array('PLANTAO_TIPO', $cols, true))
            $payload['PLANTAO_TIPO'] = $request->tipo ?? 'programado';
        if (in_array('PLANTAO_TURNO', $cols, true))
            $payload['PLANTAO_TURNO'] = $request->turno ?? (($request->tipo ?? '') === 'urgencia' ? 'U' : 'D');
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

        // Conecta a teia: toda solicitação de plantão extra entra também na fila de Hora Extra para aprovação de gestor/admin.
        $horaExtraId = null;
        if (\Illuminate\Support\Facades\Schema::hasTable('HORA_EXTRA')) {
            $lot = \Illuminate\Support\Facades\DB::table('LOTACAO as l')
                ->leftJoin('SETOR as s', 's.SETOR_ID', '=', 'l.SETOR_ID')
                ->where('l.FUNCIONARIO_ID', $funcionario->FUNCIONARIO_ID)
                ->whereNull('l.LOTACAO_DATA_FIM')
                ->select('l.SETOR_ID', 's.UNIDADE_ID')
                ->first();

            $tipoHoraExtra = (($request->tipo ?? '') === 'urgencia') ? '100_PORCENTO' : '50_PORCENTO';
            $percentual = $tipoHoraExtra === '100_PORCENTO' ? 100.0 : 50.0;

            $cargo = ! empty($funcionario->CARGO_ID)
                ? \Illuminate\Support\Facades\DB::table('CARGO')->where('CARGO_ID', $funcionario->CARGO_ID)->first()
                : null;
            $salario = 0.0;
            $chMensal = 220;
            if ($cargo) {
                $salario = (float) max(
                    (float) ($cargo->CARGO_REMUNERACAO ?? 0),
                    (float) ($cargo->CARGO_SALARIO ?? 0),
                    (float) ($cargo->CARGO_SALARIO_BASE ?? 0)
                );
                if (! empty($cargo->CARGO_CARGA_HORARIA) && (int) $cargo->CARGO_CARGA_HORARIA > 0) {
                    $chMensal = (int) $cargo->CARGO_CARGA_HORARIA;
                }
            }
            $valorHoraBase = ($salario > 0 && $chMensal > 0) ? ($salario / $chMensal) : 0.0;
            $valorCalculado = round($valorHoraBase * (1 + $percentual / 100) * (float) ($horasDuracao ?? 0), 2);

            $horaExtraId = \Illuminate\Support\Facades\DB::table('HORA_EXTRA')->insertGetId([
                'FUNCIONARIO_ID' => $funcionario->FUNCIONARIO_ID,
                'UNIDADE_ID' => $lot?->UNIDADE_ID,
                'SETOR_ID' => $payload['SETOR_ID'] ?? $lot?->SETOR_ID,
                'COMPETENCIA' => substr((string) ($request->data ?? now()->toDateString()), 0, 7),
                'DATA_REALIZACAO' => $request->data,
                'HORA_INICIO' => $horaIni,
                'HORA_FIM' => $horaFim,
                'TOTAL_HORAS' => (float) ($horasDuracao ?? 0),
                'TIPO_HORA_EXTRA' => $tipoHoraExtra,
                'PERCENTUAL' => $percentual,
                'VALOR_HORA_BASE' => round($valorHoraBase, 4),
                'VALOR_CALCULADO' => $valorCalculado,
                'STATUS' => 'PENDENTE',
                'OBSERVACAO' => 'ORIGEM_PLANTAO_EXTRA:' . $id . ' | ' . (string) ($request->justificativa ?? ''),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        return response()->json([
            'id' => $id,
            'hora_extra_id' => $horaExtraId,
            'plantao' => [
                'PLANTAO_ID' => $id,
                'PLANTAO_DATA' => $request->data,
                'PLANTAO_SETOR' => $request->setor,
                'PLANTAO_HORA_INI' => $horaIni,
                'PLANTAO_HORA_FIM' => $horaFim,
                'PLANTAO_HORAS' => $horasDuracao,
                'PLANTAO_TIPO' => $request->tipo ?? 'programado',
                'PLANTAO_STATUS' => 'PENDENTE',
            ],
        ], 201);
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
            $jp = \App\Services\Jornada\JornadaRegraParametros::class;

            return response()->json([
                'sobreaviso' => [],
                'acionamentos' => [],
                'fallback' => true,
                'parametros_jornada' => [
                    'sobreaviso_teto_h_acionamento' => $jp::tetoSobreavisoAcionamentoHoras(),
                    'sobreaviso_adicional_fracao_hora_normal' => $jp::fracaoAdicionalSobreHoraNormal(),
                    'valor_hora_referencia_rs' => $jp::valorHoraReferenciaRs(),
                ],
            ]);
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
        }

        // Fallback real: se ACIONAMENTO existir mas vier vazio, tenta legado ACIONAMENTO_SOBREAVISO.
        if ($acionamentos->isEmpty() && \Illuminate\Support\Facades\Schema::hasTable('ACIONAMENTO_SOBREAVISO')) {
            $colsAlt = \Illuminate\Support\Facades\Schema::getColumnListing('ACIONAMENTO_SOBREAVISO');
            $acionamentos = \Illuminate\Support\Facades\DB::table('ACIONAMENTO_SOBREAVISO')
                ->where('FUNCIONARIO_ID', $funcionario->FUNCIONARIO_ID)
                ->whereBetween('ACIONAMENTO_DATA', [$inicio, $fim])
                ->orderByDesc('ACIONAMENTO_DATA')
                ->get()
                ->map(function ($r) use ($colsAlt) {
                    $normalizarHora = function (?string $hora): string {
                        $raw = trim((string) ($hora ?? ''));
                        if ($raw === '') {
                            return '';
                        }
                        $raw = preg_replace('/[^0-9:]/', '', $raw);
                        if (preg_match('/^\d{1,2}$/', $raw)) {
                            return str_pad($raw, 2, '0', STR_PAD_LEFT) . ':00';
                        }
                        if (preg_match('/^\d{1,2}:\d{1,2}$/', $raw)) {
                            [$h, $m] = explode(':', $raw);
                            return str_pad($h, 2, '0', STR_PAD_LEFT) . ':' . str_pad($m, 2, '0', STR_PAD_LEFT);
                        }
                        return '';
                    };

                    $ini = $normalizarHora((string) ($r->ACIONAMENTO_HORA_INI ?? ''));
                    $fimHora = $normalizarHora((string) ($r->ACIONAMENTO_HORA_FIM ?? ''));
                    if (($ini === '' || $fimHora === '') && in_array('ACIONAMENTO_HORA', $colsAlt, true)) {
                        $hora = (string) ($r->ACIONAMENTO_HORA ?? '');
                        $parts = array_map('trim', explode('-', $hora));
                        $ini = $ini !== '' ? $ini : $normalizarHora($parts[0] ?? '');
                        $fimHora = $fimHora !== '' ? $fimHora : $normalizarHora($parts[1] ?? '');
                    }
                    $duracao = (float) ($r->ACIONAMENTO_DURACAO ?? 0);
                    if ($duracao <= 0 && $ini !== '' && $fimHora !== '') {
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
                        'ACIONAMENTO_VALOR' => $r->ACIONAMENTO_VALOR ?? null,
                        'ACIONAMENTO_PAGO' => $r->ACIONAMENTO_PAGO ?? 0,
                    ];
                });
        }

        $em = \Carbon\Carbon::parse($inicio);
        $jp = \App\Services\Jornada\JornadaRegraParametros::class;

        return response()->json([
            'sobreaviso' => $sobreaviso->values(),
            'acionamentos' => $acionamentos->values(),
            'fallback' => $sobreaviso->isEmpty() && $acionamentos->isEmpty(),
            'parametros_jornada' => [
                'sobreaviso_teto_h_acionamento' => $jp::tetoSobreavisoAcionamentoHoras($em),
                'sobreaviso_adicional_fracao_hora_normal' => $jp::fracaoAdicionalSobreHoraNormal($em),
                'valor_hora_referencia_rs' => $jp::valorHoraReferenciaRs($em),
            ],
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

        $tetoH = \App\Services\Jornada\JornadaRegraParametros::tetoSobreavisoAcionamentoHoras();
        if ($duracao > $tetoH + 0.0001) {
            return response()->json([
                'erro' => "Duração do acionamento ({$duracao}h) excede o teto de {$tetoH}h (regra de sobreaviso).",
            ], 422);
        }

        $em = new \DateTimeImmutable($dataAcion);
        $valorAcionamento = \App\Services\Jornada\JornadaRegraParametros::valorSugeridoAcionamentoSobreaviso($duracao, $em);

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

<?php
// routes/rpps.php — RPPS/IPAM
// ⚠️ NÃO abrir Route::middleware()->prefix()->group() — herda contexto api/v3 + auth do web.php (§2 das regras)
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

// Dashboard RPPS
Route::get('/rpps/dashboard', function (Request $req) {
    $comp = $req->query('competencia', date('Y-m'));
    try {
        // PERF-03 — Alíquotas dinâmicas via RPPS_CONFIG (não hardcoded)
        $config = null;
        try {
            $config = DB::table('RPPS_CONFIG')
                ->where('VIGENCIA_INICIO', '<=', $comp)
                ->where(function ($q) use ($comp) {
                    $q->whereNull('VIGENCIA_FIM')->orWhere('VIGENCIA_FIM', '>=', $comp);
                })
                ->orderByDesc('VIGENCIA_INICIO')
                ->first();
        } catch (\Throwable $ex) { /* tabela ainda não existe */
        }

        $aliqServidor = $config->ALIQUOTA_SERVIDOR ?? 14.0;
        $aliqPatronal = $config->ALIQUOTA_PATRONAL ?? 28.0;

        $totais = DB::table('RPPS_CONTRIBUICAO')
            ->where('COMPETENCIA', $comp)
            ->selectRaw('SUM(VALOR_SERVIDOR) as total_servidor, SUM(VALOR_PATRONAL) as total_patronal, COUNT(*) as qtd')
            ->first();

        $historico = DB::table('RPPS_CONTRIBUICAO')
            ->select('COMPETENCIA', DB::raw('SUM(VALOR_SERVIDOR) as servidor'), DB::raw('SUM(VALOR_PATRONAL) as patronal'))
            ->groupBy('COMPETENCIA')
            ->orderByDesc('COMPETENCIA')
            ->limit(12)
            ->get();

        return response()->json([
            'competencia' => $comp,
            'total_servidor' => $totais->total_servidor ?? 0,
            'total_patronal' => $totais->total_patronal ?? 0,
            'total_geral' => ($totais->total_servidor ?? 0) + ($totais->total_patronal ?? 0),
            'qtd_servidores' => $totais->qtd ?? 0,
            'historico' => $historico,
            'aliquota_servidor' => $aliqServidor,
            'aliquota_patronal' => $aliqPatronal,
        ]);
    } catch (\Throwable $e) {
        return response()->json(['fallback' => true, 'erro' => $e->getMessage()], 200);
    }
});

// Listagem de beneficiários
Route::get('/rpps/beneficiarios', function (Request $req) {
    try {
        $q = DB::table('RPPS_BENEFICIARIO as rb')
            ->join('FUNCIONARIO as f', 'f.FUNCIONARIO_ID', '=', 'rb.FUNCIONARIO_ID')
            ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
            ->select('rb.*', 'p.PESSOA_NOME as nome', 'f.FUNCIONARIO_MATRICULA as matricula');
        if ($req->tipo)
            $q->where('rb.TIPO', $req->tipo);
        return response()->json(['beneficiarios' => $q->paginate(50)]);
    } catch (\Throwable $e) {
        return response()->json(['fallback' => true]);
    }
});

// Calcular contribuições da competência
Route::post('/rpps/calcular', function (Request $req) {
    $req->validate(['competencia' => 'required|string']);
    $comp = $req->competencia;
    try {
        $servidores = DB::table('DETALHE_FOLHA as df')
            ->join('FOLHA as fl', 'fl.FOLHA_ID', '=', 'df.FOLHA_ID')
            ->where('fl.FOLHA_COMPETENCIA', str_replace('-', '', $comp))
            ->select('df.FUNCIONARIO_ID', DB::raw('SUM(COALESCE(df.DETALHE_BASE_PREV, df.DETALHE_FOLHA_PROVENTOS, 0)) as base')) // BUG-RPPS-01: usar base previdenciária real
            ->groupBy('df.FUNCIONARIO_ID')
            ->get();

        // PERF-03 — Aliqóuotas dinâmicas via RPPS_CONFIG (não hardcoded)
        $config = null;
        try {
            $config = DB::table('RPPS_CONFIG')
                ->where('VIGENCIA_INICIO', '<=', $comp)
                ->where(function ($q) use ($comp) {
                    $q->whereNull('VIGENCIA_FIM')->orWhere('VIGENCIA_FIM', '>=', $comp);
                })
                ->orderByDesc('VIGENCIA_INICIO')
                ->first();
        } catch (\Throwable $ex) { /* tabela ainda não existe */
        }

        $aliqServidor = ($config->ALIQUOTA_SERVIDOR ?? 14.0) / 100;
        $aliqPatronal = ($config->ALIQUOTA_PATRONAL ?? 28.0) / 100;

        $inseridos = 0;
        foreach ($servidores as $s) {
            $base = floatval($s->base);
            $srv = round($base * $aliqServidor, 2);
            $patronal = round($base * $aliqPatronal, 2);
            DB::table('RPPS_CONTRIBUICAO')->updateOrInsert(
                ['FUNCIONARIO_ID' => $s->FUNCIONARIO_ID, 'COMPETENCIA' => $comp],
                ['BASE_CALCULO' => $base, 'VALOR_SERVIDOR' => $srv, 'VALOR_PATRONAL' => $patronal, 'STATUS' => 'CALCULADO', 'updated_at' => now()]
            );
            $inseridos++;
        }
        return response()->json(['mensagem' => "RPPS calculado para {$inseridos} servidores em {$comp}.", 'qtd' => $inseridos]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
})->middleware('perfil:ADMINISTRADOR,Administrador,GESTOR');

// Exportar CADPREV (simulado)
Route::post('/rpps/exportar-cadprev', function (Request $req) {
    $req->validate(['competencia' => 'required|string']);
    $comp = $req->competencia;
    try {
        DB::table('RPPS_EXPORTACAO')->insert(['TIPO' => 'CADPREV', 'COMPETENCIA' => $comp, 'STATUS' => 'GERADO', 'created_at' => now(), 'updated_at' => now()]);
        return response()->json(['mensagem' => "Exportação CADPREV para {$comp} registrada."]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
})->middleware('perfil:ADMINISTRADOR,Administrador,GESTOR');

if (!function_exists('ensureRppsProvaVidaTables')) {
    function ensureRppsProvaVidaTables(): void
    {
        if (!Schema::hasTable('RPPS_PROVA_VIDA') || !Schema::hasTable('RPPS_BLOQUEIO_EVENTO')) {
            throw new \RuntimeException('Tabelas RPPS de prova de vida não encontradas. Execute migrations canônicas.');
        }
    }
}

if (!function_exists('rppsProvaVidaPrazoPadrao')) {
    function rppsProvaVidaPrazoPadrao(string $competencia): string
    {
        try {
            [$ano, $mes] = explode('-', $competencia);
            return Carbon::createFromDate((int) $ano, (int) $mes, 1)->endOfMonth()->toDateString();
        } catch (\Throwable $e) {
            return now()->endOfMonth()->toDateString();
        }
    }
}

// S6.1 — listar prova de vida do RPPS/IPAM
Route::get('/rpps/prova-vida', function (Request $req) {
    try {
        ensureRppsProvaVidaTables();
        $comp = (string) ($req->query('competencia', now()->format('Y-m')));
        $q = DB::table('RPPS_PROVA_VIDA as pv')
            ->join('FUNCIONARIO as f', 'f.FUNCIONARIO_ID', '=', 'pv.FUNCIONARIO_ID')
            ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
            ->where('pv.COMPETENCIA', $comp)
            ->select(
                'pv.*',
                'p.PESSOA_NOME as nome',
                'f.FUNCIONARIO_MATRICULA as matricula'
            )
            ->orderBy('p.PESSOA_NOME');

        if ($req->query('status')) {
            $q->where('pv.STATUS', $req->query('status'));
        }

        return response()->json([
            'competencia' => $comp,
            'itens' => $q->limit(3000)->get(),
        ]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// S6.1 — inicializar pendências da competência (idempotente)
Route::post('/rpps/prova-vida/inicializar', function (Request $req) {
    try {
        ensureRppsProvaVidaTables();
        $comp = (string) ($req->input('competencia', now()->format('Y-m')));
        if (!preg_match('/^\d{4}-\d{2}$/', $comp)) {
            return response()->json(['erro' => 'competencia inválida (use YYYY-MM).'], 422);
        }

        $prazo = (string) ($req->input('prazo_final') ?: rppsProvaVidaPrazoPadrao($comp));
        $inseridos = 0;
        $hoje = now()->toDateString();

        // Base preferencial: RPPS_BENEFICIARIO. Fallback: FUNCIONARIO com regime RPPS.
        $beneficiarios = collect();
        if (Schema::hasTable('RPPS_BENEFICIARIO')) {
            $beneficiarios = DB::table('RPPS_BENEFICIARIO')
                ->select('FUNCIONARIO_ID')
                ->distinct()
                ->get();
        } elseif (Schema::hasTable('FUNCIONARIO')) {
            $beneficiarios = DB::table('FUNCIONARIO')
                ->whereRaw("LOWER(COALESCE(FUNCIONARIO_REGIME_PREV,'')) like '%rpps%'")
                ->select('FUNCIONARIO_ID')
                ->get();
        }

        foreach ($beneficiarios as $b) {
            $funcId = (int) ($b->FUNCIONARIO_ID ?? 0);
            if ($funcId <= 0) {
                continue;
            }
            $exists = DB::table('RPPS_PROVA_VIDA')
                ->where('FUNCIONARIO_ID', $funcId)
                ->where('COMPETENCIA', $comp)
                ->exists();
            if ($exists) {
                continue;
            }
            DB::table('RPPS_PROVA_VIDA')->insert([
                'FUNCIONARIO_ID' => $funcId,
                'COMPETENCIA' => $comp,
                'STATUS' => 'pendente',
                'TIPO_PROCEDIMENTO' => 'ordinaria',
                'CANAL' => null,
                'DATA_REFERENCIA' => $hoje,
                'PRAZO_FINAL' => $prazo,
                'MOTIVO' => 'Pendência inicial automática da competência',
                'VALIDADO_POR' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $inseridos++;
        }

        return response()->json([
            'ok' => true,
            'competencia' => $comp,
            'prazo_final' => $prazo,
            'inseridos' => $inseridos,
        ]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
})->middleware('perfil:ADMINISTRADOR,Administrador,GESTOR');

// S6.1 — registrar validação (presencial/govbr/extraordinário)
Route::post('/rpps/prova-vida/registrar', function (Request $req) {
    try {
        ensureRppsProvaVidaTables();
        $data = $req->validate([
            'funcionario_id' => 'required|integer',
            'competencia' => 'required|string|size:7',
            'canal' => 'required|string|max:30',
            'tipo_procedimento' => 'nullable|string|max:20',
            'motivo' => 'nullable|string|max:255',
        ]);

        $user = auth()->user();
        DB::table('RPPS_PROVA_VIDA')->updateOrInsert(
            ['FUNCIONARIO_ID' => (int) $data['funcionario_id'], 'COMPETENCIA' => $data['competencia']],
            [
                'STATUS' => 'regular',
                'CANAL' => strtolower($data['canal']),
                'TIPO_PROCEDIMENTO' => strtolower((string) ($data['tipo_procedimento'] ?? 'ordinaria')),
                'DATA_REGISTRO' => now()->toDateString(),
                'MOTIVO' => $data['motivo'] ?? null,
                'VALIDADO_POR' => $user->USUARIO_ID ?? null,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        DB::table('RPPS_BLOQUEIO_EVENTO')->insert([
            'FUNCIONARIO_ID' => (int) $data['funcionario_id'],
            'COMPETENCIA' => $data['competencia'],
            'EVENTO' => 'desbloqueado',
            'ORIGEM' => 'manual',
            'MOTIVO' => 'Prova de vida regularizada',
            'USUARIO_ID' => $user->USUARIO_ID ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['ok' => true]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 422);
    }
})->middleware('perfil:ADMINISTRADOR,Administrador,GESTOR');

// S6.1 — processamento manual da competência (pendente -> bloqueio iminente/bloqueado)
Route::post('/rpps/prova-vida/processar', function (Request $req) {
    try {
        ensureRppsProvaVidaTables();
        $comp = (string) ($req->input('competencia', now()->format('Y-m')));
        $hoje = Carbon::now()->toDateString();

        $itens = DB::table('RPPS_PROVA_VIDA')->where('COMPETENCIA', $comp)->get();
        $iminentes = 0;
        $bloqueados = 0;
        foreach ($itens as $i) {
            if (($i->STATUS ?? '') === 'regular') {
                continue;
            }
            $prazo = !empty($i->PRAZO_FINAL) ? (string) $i->PRAZO_FINAL : Carbon::parse($i->DATA_REFERENCIA ?? $hoje)->endOfMonth()->toDateString();
            $novo = $hoje > $prazo ? 'bloqueado' : 'bloqueio_iminente';
            if ($novo === 'bloqueado') $bloqueados++; else $iminentes++;

            DB::table('RPPS_PROVA_VIDA')
                ->where('RPPS_PROVA_VIDA_ID', $i->RPPS_PROVA_VIDA_ID)
                ->update([
                    'STATUS' => $novo,
                    'MOTIVO' => $novo === 'bloqueado' ? 'Prazo expirado sem prova de vida' : 'Prazo próximo do vencimento',
                    'updated_at' => now(),
                ]);

            DB::table('RPPS_BLOQUEIO_EVENTO')->insert([
                'FUNCIONARIO_ID' => $i->FUNCIONARIO_ID,
                'COMPETENCIA' => $comp,
                'EVENTO' => $novo,
                'ORIGEM' => 'scheduler',
                'MOTIVO' => $novo === 'bloqueado' ? 'Processamento automático de prova de vida' : 'Alerta automático de prazo',
                'USUARIO_ID' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return response()->json([
            'ok' => true,
            'competencia' => $comp,
            'bloqueio_iminente' => $iminentes,
            'bloqueados' => $bloqueados,
        ]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
})->middleware('perfil:ADMINISTRADOR,Administrador,GESTOR');


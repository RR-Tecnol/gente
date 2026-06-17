<?php
use App\Domain\Escala\EscalaAusenciaService;
use App\Domain\Escala\EscalaWorkflowService;
use App\Domain\Escala\EscalaWorkflowStatus;
use App\Domain\Escala\MotivoAlteracaoPolicy;
use App\Services\Pccv\PccvValidatorService;
use App\Support\GenteAuditWriter;
use App\Support\GentePccvComplianceAudit;
use App\Support\PiiBlindIndex;
use App\Support\RbacResolver;
use App\Support\UnidadeEscopoUsuario;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

if (! function_exists('escalaDebugLogF94096')) {
    function escalaDebugLogF94096(string $hypothesisId, string $location, string $message, array $data = []): void
    {
        try {
            $payload = [
                'sessionId' => 'f94096',
                'runId' => 'runtime-http-validation',
                'hypothesisId' => $hypothesisId,
                'location' => $location,
                'message' => $message,
                'data' => $data,
                'timestamp' => (int) round(microtime(true) * 1000),
            ];
            @file_put_contents('/home/DK/Developer/Projects/GENTE/.cursor/debug-f94096.log', json_encode($payload, JSON_UNESCAPED_UNICODE).PHP_EOL, FILE_APPEND);
        } catch (\Throwable $e) {
            // no-op em modo debug
        }
    }
}

Route::get('/motivos-alteracao-escala', function () {
    try {
        if (! Schema::hasTable('MOTIVO_ALTERACAO_DOMINIO')) {
            return response()->json(['motivos' => []]);
        }
        $temSigla = Schema::hasColumn('MOTIVO_ALTERACAO_DOMINIO', 'SIGLA');
        $select = [
            'MOTIVO_ALTERACAO_ID as id',
            'TITULO as titulo',
            'DESCRICAO as descricao',
            'EXIGE_DOCUMENTO as exige_documento',
        ];
        if ($temSigla) {
            $select[] = 'SIGLA as sigla';
        }
        $rows = DB::table('MOTIVO_ALTERACAO_DOMINIO')
            ->where('ATIVO', 1)
            ->orderBy('TITULO')
            ->get($select);

        return response()->json([
            'motivos' => $rows->map(function ($r) use ($temSigla) {
                $row = (object) [
                    'TITULO' => $r->titulo,
                    'SIGLA' => ($temSigla && property_exists($r, 'sigla')) ? $r->sigla : null,
                    'EXIGE_DOCUMENTO' => $r->exige_documento,
                ];
                $meta = MotivoAlteracaoPolicy::metadadosNormativos($row);
                $exige = MotivoAlteracaoPolicy::exigeDocumentoReferencia($row);

                return [
                    'id' => (int) $r->id,
                    'sigla' => ($temSigla && property_exists($r, 'sigla') && $r->sigla !== null) ? (string) $r->sigla : null,
                    'titulo' => MotivoAlteracaoPolicy::tituloParaExibicao($row),
                    'descricao' => $r->descricao !== null ? (string) $r->descricao : null,
                    'exige_documento' => $exige,
                    'base_legal' => $meta['base_legal'],
                    'impacto_financeiro' => $meta['impacto_financeiro'],
                ];
            })->values()->all(),
        ]);
    } catch (\Throwable $e) {
        return response()->json(['motivos' => [], 'erro' => $e->getMessage()], 500);
    }
});

Route::get('/escala-trabalho', function (\Illuminate\Http\Request $request) {
    try {
        $hoje = now()->toDateString();
        $mes = (int) ($request->mes ?? now()->month);
        $ano = (int) ($request->ano ?? now()->year);
        $comp = sprintf('%04d-%02d', $ano, $mes);
        $setorId = $request->filled('setor_id') ? (int) $request->setor_id : null;
        $carregarTudo = filter_var((string) $request->input('carregar_tudo', '0'), FILTER_VALIDATE_BOOLEAN);
        $somenteSaude = filter_var((string) $request->input('somente_saude', '0'), FILTER_VALIDATE_BOOLEAN);
        $perPage = max(10, min(200, (int) $request->input('per_page', 50)));
        $page = max(1, (int) $request->input('page', 1));
        $usuario = auth()->user();
        $permitidos = UnidadeEscopoUsuario::setorIdsPermitidos($usuario, $request);
        if ($permitidos === []) {
            return response()->json([
                'erro' => 'Sem unidade/secretaria vinculada ao seu usuário para acessar a escala (USUARIO_UNIDADE) ou escopo vazio.',
                'competencia' => $comp,
                'escala' => [],
                'setores' => [],
                'funcionarios' => [],
                'workflow' => null,
            ], 403);
        }
        if ($setorId && $permitidos !== null && ! in_array($setorId, $permitidos, true)) {
            return response()->json([
                'erro' => 'Setor fora do seu escopo de unidade (USUARIO_UNIDADE).',
                'competencia' => $comp,
                'escala' => [],
                'setores' => [],
                'funcionarios' => [],
                'workflow' => null,
            ], 403);
        }

        $setoresSelect = [
            's.SETOR_ID as id',
            's.SETOR_NOME as nome',
            's.UNIDADE_ID as unidade_id',
        ];
        $comUnidade = Schema::hasTable('UNIDADE');
        if ($comUnidade && Schema::hasColumn('UNIDADE', 'UNIDADE_NOME')) {
            $setoresSelect[] = 'u.UNIDADE_NOME as unidade_nome';
        }
        if ($comUnidade && Schema::hasColumn('UNIDADE', 'UNIDADE_SIGLA')) {
            $setoresSelect[] = 'u.UNIDADE_SIGLA as unidade_sigla';
        }
        $setoresQ = DB::table('SETOR as s')
            ->when($comUnidade, function ($q) {
                $q->leftJoin('UNIDADE as u', 'u.UNIDADE_ID', '=', 's.UNIDADE_ID');
            })
            ->select($setoresSelect)
            ->when(
                Schema::hasColumn('SETOR', 'SETOR_ATIVO'),
                fn ($q) => $q->where('s.SETOR_ATIVO', 1)
            )
            ->when($permitidos !== null, fn ($q) => $q->whereIn('s.SETOR_ID', $permitidos))
            ->orderBy('s.SETOR_NOME');
        $setores = $setoresQ->get();

        if (! $carregarTudo && ! $setorId) {
            return response()->json([
                'competencia' => $comp,
                'escala' => [],
                'setores' => $setores,
                'funcionarios' => [],
                'workflow' => EscalaWorkflowService::montarPayloadWorkflowMacro($request),
                'paginacao' => ['page' => 1, 'per_page' => $perPage, 'total' => 0, 'last_page' => 1],
                'erro' => null,
                'hint' => 'Selecione um setor para carregar a escala (Filtro Ativo). Use carregar_tudo=1 para visão macro paginada.',
            ]);
        }

        $temLotacaoFim = \Illuminate\Support\Facades\Schema::hasColumn('LOTACAO', 'LOTACAO_DATA_FIM');
        $temFuncionarioFim = \Illuminate\Support\Facades\Schema::hasColumn('FUNCIONARIO', 'FUNCIONARIO_DATA_FIM');
        $temObsItemEscala = \Illuminate\Support\Facades\Schema::hasColumn('DETALHE_ESCALA_ITEM', 'DETALHE_ESCALA_ITEM_OBS');

        $cpfHashFiltro = null;
        if (\Illuminate\Support\Facades\Schema::hasColumn('PESSOA', 'PESSOA_CPF_HASH') && $request->filled('cpf')) {
            $d = preg_replace('/\D+/', '', (string) $request->input('cpf'));
            if (strlen($d) === 11) {
                $cpfHashFiltro = PiiBlindIndex::cpfHash($d);
            }
        }

        $applySaudeFilter = function ($q) use ($somenteSaude) {
            if (! $somenteSaude) {
                return;
            }
            $q->where(function ($w) {
                if (Schema::hasColumn('CARGO', 'CARGO_AREA')) {
                    $w->orWhereRaw('UPPER(CAST(c.CARGO_AREA AS VARCHAR(255))) LIKE ?', ['%SAUDE%']);
                }
                if (Schema::hasColumn('CARGO', 'AREA')) {
                    $w->orWhereRaw('UPPER(CAST(c.AREA AS VARCHAR(255))) LIKE ?', ['%SAUDE%']);
                }
                if (Schema::hasColumn('CARGO', 'CARGO_TIPO')) {
                    $w->orWhereRaw('UPPER(CAST(c.CARGO_TIPO AS VARCHAR(255))) LIKE ?', ['%SAUDE%']);
                }
                if (Schema::hasColumn('CARGO', 'CBO')) {
                    $w->orWhereRaw('CAST(c.CBO AS VARCHAR(255)) LIKE ?', ['223%']);
                }
                if (Schema::hasColumn('CARGO', 'CARGO_CBO')) {
                    $w->orWhereRaw('CAST(c.CARGO_CBO AS VARCHAR(255)) LIKE ?', ['223%']);
                }
                $w->orWhereRaw('UPPER(COALESCE(c.CARGO_NOME, \'\')) LIKE ?', ['%MEDIC%']);
                $w->orWhereRaw('UPPER(COALESCE(c.CARGO_NOME, \'\')) LIKE ?', ['%ENFERM%']);
                $w->orWhereRaw('UPPER(COALESCE(c.CARGO_NOME, \'\')) LIKE ?', ['%UTI%']);
            });
        };

        $funcionariosQ = DB::table('FUNCIONARIO as f')
            ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
            ->leftJoin('CARGO as c', 'c.CARGO_ID', '=', 'f.CARGO_ID')
            ->leftJoin('LOTACAO as l', function ($join) use ($temLotacaoFim) {
                $join->on('l.FUNCIONARIO_ID', '=', 'f.FUNCIONARIO_ID');
                if ($temLotacaoFim) {
                    $join->whereNull('l.LOTACAO_DATA_FIM');
                }
            })
            ->leftJoin('SETOR as s', 's.SETOR_ID', '=', 'l.SETOR_ID')
            ->leftJoin('SETOR as sp', 'sp.SETOR_ID', '=', 's.SETOR_PAI_ID')
            ->leftJoin('UNIDADE as u', 'u.UNIDADE_ID', '=', 's.UNIDADE_ID')
            ->when($temFuncionarioFim, fn($q) => $q->where(function ($w) use ($hoje) {
                $w->whereNull('f.FUNCIONARIO_DATA_FIM')
                    ->orWhere('f.FUNCIONARIO_DATA_FIM', '>', $hoje);
            }))
            ->whereNotNull('l.LOTACAO_ID')
            ->when($permitidos !== null, fn($q) => $q->whereIn('l.SETOR_ID', $permitidos))
            ->when($setorId, fn($q) => $q->where('l.SETOR_ID', $setorId))
            ->when($cpfHashFiltro, fn($q) => $q->where('p.PESSOA_CPF_HASH', $cpfHashFiltro))
            ->when(true, $applySaudeFilter)
            ->orderBy('p.PESSOA_NOME')
            ->select(
                'f.FUNCIONARIO_ID as id',
                'p.PESSOA_NOME as nome',
                DB::raw('MAX(s.SETOR_NOME) as setor'),
                DB::raw('MAX(sp.SETOR_NOME) as setor_pai'),
                DB::raw('MAX(u.UNIDADE_NOME) as unidade_nome'),
                DB::raw('MAX(c.CARGO_NOME) as cargo_nome')
            )
            ->groupBy('f.FUNCIONARIO_ID', 'p.PESSOA_NOME');
        $funcionariosPage = $funcionariosQ->paginate($perPage, ['*'], 'page', $page);
        $funcionarios = collect($funcionariosPage->items())->map(function ($f) {
            $trilha = array_values(array_filter([
                $f->unidade_nome ?? null,
                $f->setor_pai ?? null,
                $f->setor ?? null,
            ]));
            return [
                'id' => (int) $f->id,
                'nome' => (string) $f->nome,
                'setor' => (string) ($f->setor ?? 'Sem setor'),
                'cargo' => (string) ($f->cargo_nome ?? ''),
                'lotacao_trilha' => $trilha,
                'lotacao_breadcrumb' => $trilha ? implode(' > ', $trilha) : 'Sem lotação',
            ];
        })->values()->all();
        $funcIdsPagina = collect($funcionarios)->pluck('id')->all();

        $rows = DB::table('ESCALA as e')
            ->join('DETALHE_ESCALA as de', 'de.ESCALA_ID', '=', 'e.ESCALA_ID')
            ->join('FUNCIONARIO as f', 'f.FUNCIONARIO_ID', '=', 'de.FUNCIONARIO_ID')
            ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
            ->leftJoin('CARGO as c', 'c.CARGO_ID', '=', 'f.CARGO_ID')
            ->leftJoin('LOTACAO as l', function ($join) use ($temLotacaoFim) {
                $join->on('l.FUNCIONARIO_ID', '=', 'f.FUNCIONARIO_ID');
                if ($temLotacaoFim) {
                    $join->whereNull('l.LOTACAO_DATA_FIM');
                }
            })
            ->leftJoin('SETOR as s', 's.SETOR_ID', '=', 'l.SETOR_ID')
            ->leftJoin('SETOR as sp', 'sp.SETOR_ID', '=', 's.SETOR_PAI_ID')
            ->leftJoin('UNIDADE as u', 'u.UNIDADE_ID', '=', 's.UNIDADE_ID')
            ->leftJoin('DETALHE_ESCALA_ITEM as dei', 'dei.DETALHE_ESCALA_ID', '=', 'de.DETALHE_ESCALA_ID')
            ->leftJoin('TURNO as t', 't.TURNO_ID', '=', 'dei.TURNO_ID')
            ->where('e.ESCALA_COMPETENCIA', $comp)
            ->when($temFuncionarioFim, fn($q) => $q->where(function ($w) use ($hoje) {
                $w->whereNull('f.FUNCIONARIO_DATA_FIM')
                    ->orWhere('f.FUNCIONARIO_DATA_FIM', '>', $hoje);
            }))
            ->whereNotNull('l.LOTACAO_ID')
            ->when($permitidos !== null, fn($q) => $q->whereIn('e.SETOR_ID', $permitidos))
            ->when($setorId, fn($q) => $q->where('e.SETOR_ID', $setorId))
            ->when($cpfHashFiltro, fn($q) => $q->where('p.PESSOA_CPF_HASH', $cpfHashFiltro))
            ->when(true, $applySaudeFilter)
            ->when(! empty($funcIdsPagina), fn($q) => $q->whereIn('de.FUNCIONARIO_ID', $funcIdsPagina))
            ->orderBy('p.PESSOA_NOME')
            ->select(
                'de.DETALHE_ESCALA_ID as detalhe_id',
                'de.FUNCIONARIO_ID as funcionario_id',
                'p.PESSOA_NOME as nome',
                DB::raw('COALESCE(MAX(s.SETOR_NOME), \'Sem setor\') as setor_nome'),
                DB::raw('MAX(sp.SETOR_NOME) as setor_pai_nome'),
                DB::raw('MAX(u.UNIDADE_NOME) as unidade_nome'),
                DB::raw('MAX(c.CARGO_NOME) as cargo_nome'),
                'dei.DETALHE_ESCALA_ITEM_DATA as data_item',
                't.TURNO_SIGLA as turno_sigla',
                $temObsItemEscala ? DB::raw('MAX(dei.DETALHE_ESCALA_ITEM_OBS) as obs_item') : DB::raw('NULL as obs_item')
            )
            ->groupBy(
                'de.DETALHE_ESCALA_ID',
                'de.FUNCIONARIO_ID',
                'p.PESSOA_NOME',
                'dei.DETALHE_ESCALA_ITEM_DATA',
                't.TURNO_SIGLA'
            )
            ->get();

        $linhas = [];
        foreach ($rows as $r) {
            $funcId = (int) ($r->funcionario_id ?? 0);
            if (!isset($linhas[$funcId])) {
                $trilha = array_values(array_filter([
                    $r->unidade_nome ?? null,
                    $r->setor_pai_nome ?? null,
                    $r->setor_nome ?? null,
                ]));
                $linhas[$funcId] = [
                    'funcionario_id' => $funcId,
                    'nome' => $r->nome ?? 'Funcionário',
                    'setor' => $r->setor_nome ?? 'Sem setor',
                    'cargo' => $r->cargo_nome ?? '',
                    'lotacao_trilha' => $trilha,
                    'lotacao_breadcrumb' => $trilha ? implode(' > ', $trilha) : 'Sem lotação',
                    'dias' => [],
                ];
            }
            if (!empty($r->data_item)) {
                $dia = (int) date('d', strtotime($r->data_item));
                if ($dia > 0) {
                    $linhas[$funcId]['dias'][$dia] = [
                        'turno' => $r->turno_sigla ?? '',
                        'obs' => $r->obs_item ?? '',
                    ];
                }
            }
        }

        if (
            config('gente.escala.kanban_incluir_elegiveis_sem_detalhe', true)
            && $permitidos === null
            && $usuario
        ) {
            $elegQ = DB::table('FUNCIONARIO as f')
                ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
                ->join('LOTACAO as l', 'l.FUNCIONARIO_ID', '=', 'f.FUNCIONARIO_ID')
                ->join('ESCALA as e', function ($join) use ($comp) {
                    $join->on('e.SETOR_ID', '=', 'l.SETOR_ID')
                        ->where('e.ESCALA_COMPETENCIA', $comp);
                })
                ->leftJoin('SETOR as s', 's.SETOR_ID', '=', 'l.SETOR_ID')
                ->leftJoin('DETALHE_ESCALA as de', function ($join) {
                    $join->on('de.ESCALA_ID', '=', 'e.ESCALA_ID')
                        ->on('de.FUNCIONARIO_ID', '=', 'f.FUNCIONARIO_ID');
                });
            if ($temLotacaoFim) {
                $elegQ->whereNull('l.LOTACAO_DATA_FIM');
            }
            if ($temFuncionarioFim) {
                $elegQ->where(function ($w) use ($hoje) {
                    $w->whereNull('f.FUNCIONARIO_DATA_FIM')
                        ->orWhere('f.FUNCIONARIO_DATA_FIM', '>', $hoje);
                });
            }
            if ($setorId) {
                $elegQ->where('l.SETOR_ID', $setorId);
            }
            if ($cpfHashFiltro) {
                $elegQ->where('p.PESSOA_CPF_HASH', $cpfHashFiltro);
            }
            $elegQ->whereNull('de.DETALHE_ESCALA_ID');
            $maxEleg = (int) config('gente.escala.kanban_elegiveis_max', 0);
            if ($maxEleg > 0) {
                $elegQ->limit($maxEleg);
            }
            $elegiveis = $elegQ->orderBy('p.PESSOA_NOME')
                ->get([
                    'f.FUNCIONARIO_ID as funcionario_id',
                    'p.PESSOA_NOME as nome',
                    's.SETOR_NOME as setor_nome',
                ]);
            foreach ($elegiveis as $ev) {
                $fid = (int) ($ev->funcionario_id ?? 0);
                if ($fid <= 0 || isset($linhas[$fid])) {
                    continue;
                }
                $linhas[$fid] = [
                    'funcionario_id' => $fid,
                    'nome' => $ev->nome ?? 'Funcionário',
                    'setor' => $ev->setor_nome !== null && $ev->setor_nome !== '' ? (string) $ev->setor_nome : 'Sem setor',
                    'dias' => [],
                ];
            }
        }

        $funcIdsAusencias = array_values(array_unique(array_map('intval', array_keys($linhas))));
        $ausenciasIndex = EscalaAusenciaService::indexarPorFuncionarioDia($funcIdsAusencias, $comp);
        $comTurnoPlanejado = 0;
        foreach ($ausenciasIndex as $funcIdAus => $diasAus) {
            if (! isset($linhas[$funcIdAus])) {
                continue;
            }
            foreach ($diasAus as $diaNum => $aus) {
                $diaInt = (int) $diaNum;
                if ($diaInt <= 0) {
                    continue;
                }
                $cel = $linhas[$funcIdAus]['dias'][$diaInt] ?? ['turno' => '', 'obs' => ''];
                $turnoPlanejado = trim((string) ($cel['turno'] ?? ''));
                if ($turnoPlanejado !== '' && ! isset($cel['turno_planejado'])) {
                    $cel['turno_planejado'] = $turnoPlanejado;
                    $comTurnoPlanejado++;
                }
                $cel['afastamento'] = $aus;
                $cel['bloqueada_por_afastamento'] = true;
                $linhas[$funcIdAus]['dias'][$diaInt] = $cel;
            }
        }
        // #region agent log
        escalaDebugLogF94096('h3', 'escala_trabalho.php:401', 'Resumo merge afastamento no GET', [
            'competencia' => $comp,
            'linhas_count' => count($linhas),
            'func_ids_ausencias_count' => count($funcIdsAusencias),
            'func_com_ausencia_count' => count($ausenciasIndex),
            'celulas_com_turno_planejado' => $comTurnoPlanejado,
        ]);
        // #endregion

        $workflow = null;
        if ($setorId) {
            $cabWf = DB::table('ESCALA')
                ->where('ESCALA_COMPETENCIA', $comp)
                ->where('SETOR_ID', $setorId)
                ->first();
            $workflow = EscalaWorkflowService::montarPayloadApi($cabWf, $setorId, $request);
        } elseif ($carregarTudo) {
            $workflow = EscalaWorkflowService::montarPayloadWorkflowMacro($request);
        }

        return response()->json([
            'competencia' => $comp,
            'escala' => array_values($linhas),
            'setores' => $setores,
            'funcionarios' => $funcionarios,
            'workflow' => $workflow,
            'paginacao' => [
                'page' => $funcionariosPage->currentPage(),
                'per_page' => $funcionariosPage->perPage(),
                'total' => $funcionariosPage->total(),
                'last_page' => $funcionariosPage->lastPage(),
            ],
        ]);
    } catch (\Throwable $e) {
        return response()->json([
            'competencia' => sprintf('%04d-%02d', (int) ($request->ano ?? now()->year), (int) ($request->mes ?? now()->month)),
            'escala' => [],
            'setores' => [],
            'funcionarios' => [],
            'workflow' => null,
            'erro' => $e->getMessage(),
        ]);
    }
});

Route::middleware(['semad.escala.readonly'])->group(function () {
Route::post('/escala-trabalho', function (\Illuminate\Http\Request $request) {
    $request->validate([
        'funcionario_id' => 'required|integer',
        'data' => 'required|date',
        'turno' => 'nullable|string|max:10',
        'status' => 'nullable|string|max:40',
        'setor_id' => 'nullable|integer',
        'observacao' => 'nullable|string|max:500',
        'motivo_id' => 'nullable|integer|min:1',
        'documento_referencia' => 'nullable|string|max:200',
        'observacao_adicional' => 'nullable|string|max:500',
        'justificativa_legal' => 'nullable|string|max:5000',
    ]);

    try {
        GenteAuditWriter::requireAuthenticatedUserId();
        $payload = DB::transaction(function () use ($request) {
            $pccvExcecao = null;
            $dataEscala = \Carbon\Carbon::parse($request->data)->toDateString();
            $competencia = \Carbon\Carbon::parse($dataEscala)->format('Y-m');
            $funcionarioId = (int) $request->funcionario_id;
            $status = strtolower(trim((string) ($request->status ?? '')));
            $turnoSigla = strtoupper(trim((string) ($request->turno ?? '')));
            $obsLivre = trim((string) ($request->input('observacao', '')));
            $docRef = trim((string) ($request->input('documento_referencia', '')));
            $obsAdic = trim((string) ($request->input('observacao_adicional', '')));
            $motivoId = $request->filled('motivo_id') ? (int) $request->motivo_id : null;
            $motivoRow = null;
            if (Schema::hasTable('MOTIVO_ALTERACAO_DOMINIO') && $motivoId) {
                $motivoRow = DB::table('MOTIVO_ALTERACAO_DOMINIO')
                    ->where('MOTIVO_ALTERACAO_ID', $motivoId)
                    ->where('ATIVO', 1)
                    ->first();
                if (! $motivoRow) {
                    throw new \RuntimeException('Motivo de alteração inválido ou inativo.');
                }
            }

            MotivoAlteracaoPolicy::assertDocumentoReferencia($motivoRow, $docRef);

            if ($motivoRow) {
                $tituloMotivo = MotivoAlteracaoPolicy::tituloParaExibicao($motivoRow);
                $partes = array_filter([
                    '[' . $tituloMotivo . ']',
                    $docRef !== '' ? ('Ref. ' . $docRef . '.') : null,
                    $obsLivre !== '' ? $obsLivre : null,
                    $obsAdic !== '' ? $obsAdic : null,
                ]);
                $observacaoEfetiva = trim(implode(' ', $partes));
            } else {
                $observacaoEfetiva = trim($obsLivre . ' ' . $obsAdic);
            }

            $isDeleteAction = $turnoSigla === '';
            $hoje = now()->toDateString();
            if ($status !== '') {
                $statusToTurno = [
                    'disponivel' => 'F',
                    'em_regencia' => 'M',
                    'atividade_extraclasse' => 'SO',
                    'afastado_licenca' => 'AT',
                    'afastado' => 'AT',
                ];
                $turnoSigla = $statusToTurno[$status] ?? $turnoSigla;
            }

            if ($dataEscala < $hoje) {
                throw new \RuntimeException('Bloqueio de compliance: não é permitido alterar escala em data passada.');
            }

            $setorIdPayload = $request->filled('setor_id') ? (int) $request->setor_id : null;
            $lotacaoAtiva = DB::table('LOTACAO as l')
                ->where('l.FUNCIONARIO_ID', $funcionarioId)
                ->when(
                    \Illuminate\Support\Facades\Schema::hasColumn('LOTACAO', 'LOTACAO_DATA_FIM'),
                    fn($q) => $q->whereNull('l.LOTACAO_DATA_FIM')
                )
                ->orderByDesc('l.LOTACAO_ID')
                ->first(['l.SETOR_ID']);
            $setorId = $setorIdPayload ?: (int) ($lotacaoAtiva->SETOR_ID ?? 0);

            if (!$lotacaoAtiva && $status !== 'afastado' && $status !== 'afastado_licenca') {
                throw new \RuntimeException('Servidor em limbo: sem lotação ativa para entrar na escala.');
            }
            if ($setorIdPayload && $lotacaoAtiva && (int) $lotacaoAtiva->SETOR_ID !== $setorIdPayload) {
                throw new \RuntimeException('Setor da escala divergente da lotação ativa do servidor.');
            }
            if ($status === 'em_regencia' || in_array($turnoSigla, ['M', 'V', 'N', 'I', 'SO'], true)) {
                $carga = null;
                if (\Illuminate\Support\Facades\Schema::hasColumn('FUNCIONARIO', 'FUNCIONARIO_CARGA_HORARIA')) {
                    $carga = DB::table('FUNCIONARIO')->where('FUNCIONARIO_ID', $funcionarioId)->value('FUNCIONARIO_CARGA_HORARIA');
                } elseif (\Illuminate\Support\Facades\Schema::hasTable('CARGO')
                    && \Illuminate\Support\Facades\Schema::hasColumn('FUNCIONARIO', 'CARGO_ID')
                    && \Illuminate\Support\Facades\Schema::hasColumn('CARGO', 'CARGO_CARGA_HORARIA')) {
                    $carga = DB::table('FUNCIONARIO as f')
                        ->leftJoin('CARGO as c', 'c.CARGO_ID', '=', 'f.CARGO_ID')
                        ->where('f.FUNCIONARIO_ID', $funcionarioId)
                        ->value('c.CARGO_CARGA_HORARIA');
                }
                if ($carga !== null && !in_array((int) $carga, [20, 24, 40], true)) {
                    throw new \RuntimeException('Carga horária incompatível para regência (permitido: 20h, 24h, 40h).');
                }
            }
            if (! $setorId) {
                throw new \RuntimeException('Não foi possível determinar o setor; o servidor precisa de lotação ativa ou informe setor_id na requisição.');
            }
            UnidadeEscopoUsuario::abortoSeSetorNaoAutorizado(auth()->user(), $setorId, $request);

            $escala = DB::table('ESCALA')
                ->where('ESCALA_COMPETENCIA', $competencia)
                ->when($setorId, fn($q) => $q->where('SETOR_ID', $setorId))
                ->first();

            if (!$escala) {
                $escalaInsert = [
                    'ESCALA_COMPETENCIA' => $competencia,
                    'SETOR_ID' => $setorId ?: 1,
                ];
                if (\Illuminate\Support\Facades\Schema::hasColumn('ESCALA', 'ESCALA_ATIVO')) {
                    $escalaInsert['ESCALA_ATIVO'] = 1;
                }
                if (\Illuminate\Support\Facades\Schema::hasColumn('ESCALA', 'ESCALA_OBSERVACAO')) {
                    $escalaInsert['ESCALA_OBSERVACAO'] = 'Criada via Escala de Trabalho';
                }
                if (Schema::hasColumn('ESCALA', 'ESCALA_STATUS')) {
                    $escalaInsert['ESCALA_STATUS'] = EscalaWorkflowStatus::RASCUNHO;
                }
                if (Schema::hasColumn('ESCALA', 'created_at')) {
                    $escalaInsert['created_at'] = now();
                }
                if (Schema::hasColumn('ESCALA', 'updated_at')) {
                    $escalaInsert['updated_at'] = now();
                }
                $escalaId = DB::table('ESCALA')->insertGetId($escalaInsert);
            } else {
                $escalaId = $escala->ESCALA_ID;
            }

            $escalaCab = DB::table('ESCALA')->where('ESCALA_ID', $escalaId)->first();
            EscalaWorkflowService::assertPodeEditarGrade(EscalaWorkflowService::rowToArray($escalaCab), $request);
            $ctxInterv = EscalaWorkflowService::contextoIntervencaoGrade($request, $escalaCab);
            $gradeEditBypass = $ctxInterv['bypass_usado'];
            $stNorm = $ctxInterv['status_normalizado'];
            $bloqueadaPorAfastamento = EscalaAusenciaService::bloqueadaPorAfastamento($funcionarioId, $dataEscala);
            // #region agent log
            escalaDebugLogF94096('h4', 'escala_trabalho.php:585', 'Checagem bloqueio por afastamento no POST', [
                'funcionario_id' => $funcionarioId,
                'data' => $dataEscala,
                'bloqueada_por_afastamento' => $bloqueadaPorAfastamento,
            ]);
            // #endregion
            if ($bloqueadaPorAfastamento) {
                throw new \RuntimeException('Dia bloqueado por afastamento ativo. Ajuste o afastamento antes de editar a escala.');
            }

            $rawStatus = strtoupper(trim((string) ($escalaCab->ESCALA_STATUS ?? '')));
            $isHomologOrPublicada = in_array($rawStatus, [
                'PUBLICADA', 'PUBLICADO', 'HOMOLOGADA',
                EscalaWorkflowStatus::HOMOLOGADO_SAGEP,
            ], true);
            if ($isDeleteAction && $isHomologOrPublicada && $observacaoEfetiva === '') {
                throw new \RuntimeException('Escala homologada/publicada exige justificativa com motivo documentado para remover turno.');
            }

            $detalhe = DB::table('DETALHE_ESCALA')
                ->where('ESCALA_ID', $escalaId)
                ->where('FUNCIONARIO_ID', $funcionarioId)
                ->first();
            if (!$detalhe) {
                $detalheId = DB::table('DETALHE_ESCALA')->insertGetId([
                    'ESCALA_ID' => $escalaId,
                    'FUNCIONARIO_ID' => $funcionarioId,
                ]);
            } else {
                $detalheId = $detalhe->DETALHE_ESCALA_ID;
            }

            $temTurnoSiglaNoItem = \Illuminate\Support\Facades\Schema::hasColumn('DETALHE_ESCALA_ITEM', 'TURNO_SIGLA');
            $temObsNoItem = \Illuminate\Support\Facades\Schema::hasColumn('DETALHE_ESCALA_ITEM', 'DETALHE_ESCALA_ITEM_OBS');
            $temUpdatedAtItem = \Illuminate\Support\Facades\Schema::hasColumn('DETALHE_ESCALA_ITEM', 'updated_at');
            $temMotivoItem = \Illuminate\Support\Facades\Schema::hasColumn('DETALHE_ESCALA_ITEM', 'MOTIVO_ALTERACAO_ID');
            $temDocItem = \Illuminate\Support\Facades\Schema::hasColumn('DETALHE_ESCALA_ITEM', 'DOCUMENTO_REFERENCIA');

            // Sigla canônica vem de TURNO; só inclui i.TURNO_SIGLA no SELECT se a coluna existir no item (legado).
            $selectItemAtual = [
                'i.DETALHE_ESCALA_ITEM_ID',
                'i.TURNO_ID',
                't.TURNO_SIGLA as TURNO_SIGLA_JOIN',
            ];
            if ($temTurnoSiglaNoItem) {
                $selectItemAtual[] = 'i.TURNO_SIGLA';
            }

            $itemAtual = DB::table('DETALHE_ESCALA_ITEM as i')
                ->leftJoin('TURNO as t', 't.TURNO_ID', '=', 'i.TURNO_ID')
                ->where('i.DETALHE_ESCALA_ID', $detalheId)
                ->where('i.DETALHE_ESCALA_ITEM_DATA', $dataEscala)
                ->first($selectItemAtual);

            if ($isDeleteAction) {
                if ($itemAtual) {
                    $payloadDelete = [
                        'TURNO_ID' => null,
                    ];
                    if ($temUpdatedAtItem) {
                        $payloadDelete['updated_at'] = now();
                    }
                    if ($temTurnoSiglaNoItem) {
                        $payloadDelete['TURNO_SIGLA'] = null;
                    }
                    if ($temObsNoItem) {
                        $payloadDelete['DETALHE_ESCALA_ITEM_OBS'] = $observacaoEfetiva !== '' ? $observacaoEfetiva : 'Turno removido (modo borracha)';
                    }
                    if ($temMotivoItem) {
                        $payloadDelete['MOTIVO_ALTERACAO_ID'] = $motivoId ?: null;
                    }
                    if ($temDocItem) {
                        $payloadDelete['DOCUMENTO_REFERENCIA'] = $docRef !== '' ? $docRef : null;
                    }
                    DB::table('DETALHE_ESCALA_ITEM')
                        ->where('DETALHE_ESCALA_ITEM_ID', $itemAtual->DETALHE_ESCALA_ITEM_ID)
                        ->update($payloadDelete);
                }
            } else {
                $aliases = [$turnoSigla];
                if ($turnoSigla === 'V') {
                    $aliases[] = 'T';
                }
                if ($turnoSigla === 'T') {
                    $aliases[] = 'V';
                }
                if ($turnoSigla === 'AT') {
                    $aliases[] = 'AF';
                }
                if ($turnoSigla === 'AF') {
                    $aliases[] = 'AT';
                }
                $turnoId = DB::table('TURNO')->whereIn('TURNO_SIGLA', $aliases)->value('TURNO_ID');
                if (!$turnoId) {
                    throw new \RuntimeException("Turno '{$turnoSigla}' não encontrado.");
                }

                $pccv = app(PccvValidatorService::class);
                if ($pccv->isEnabled()) {
                    $v = $pccv->validarCelulaEscala(
                        $funcionarioId,
                        (int) $escalaId,
                        $competencia,
                        $dataEscala,
                        (int) $turnoId
                    );
                    if ($v !== null) {
                        $jl = trim((string) $request->input('justificativa_legal', ''));
                        $minJ = (int) config('gente.pccv.min_justificativa_chars', 20);
                        if ($jl === '' || mb_strlen($jl) < $minJ) {
                            throw new HttpResponseException(
                                response()->json(
                                    [
                                        'ok' => false,
                                        'message' => 'Conformidade PCCV: carga horária semanal excedida. Justificativa legal obrigatória (mín. '.$minJ.' caracteres).',
                                        'code' => 'PCCV_ESCALA_VIOLATION',
                                        'infracoes' => [$v->toArray()],
                                    ],
                                    422
                                )
                            );
                        }
                        $pccvExcecao = ['v' => $v, 'texto' => $jl];
                    }
                }

                $payloadUpsert = [
                    'TURNO_ID' => $turnoId,
                ];
                if ($temUpdatedAtItem) {
                    $payloadUpsert['updated_at'] = now();
                }
                if ($temTurnoSiglaNoItem) {
                    $payloadUpsert['TURNO_SIGLA'] = $turnoSigla;
                }
                if ($temObsNoItem) {
                    $payloadUpsert['DETALHE_ESCALA_ITEM_OBS'] = $observacaoEfetiva !== '' ? $observacaoEfetiva : null;
                }
                if ($temMotivoItem) {
                    $payloadUpsert['MOTIVO_ALTERACAO_ID'] = $motivoId ?: null;
                }
                if ($temDocItem) {
                    $payloadUpsert['DOCUMENTO_REFERENCIA'] = $docRef !== '' ? $docRef : null;
                }
                DB::table('DETALHE_ESCALA_ITEM')->updateOrInsert(
                    [
                        'DETALHE_ESCALA_ID' => $detalheId,
                        'DETALHE_ESCALA_ITEM_DATA' => $dataEscala,
                    ],
                    $payloadUpsert
                );
            }

            if (\Illuminate\Support\Facades\Schema::hasTable('AUDIT_LOG')) {
                $auditCols = \Illuminate\Support\Facades\Schema::getColumnListing('AUDIT_LOG');
                $byLower = [];
                foreach ($auditCols as $c) {
                    $byLower[strtolower($c)] = $c;
                }
                $pickCol = function (string ...$candidates) use ($byLower): ?string {
                    foreach ($candidates as $name) {
                        $k = $byLower[strtolower($name)] ?? null;
                        if ($k !== null) {
                            return $k;
                        }
                    }

                    return null;
                };

                $metaAudit = $motivoRow
                    ? MotivoAlteracaoPolicy::metadadosNormativos($motivoRow)
                    : ['base_legal' => null, 'impacto_financeiro' => null];
                $siglaAudit = ($motivoRow && property_exists($motivoRow, 'SIGLA') && $motivoRow->SIGLA !== null && trim((string) $motivoRow->SIGLA) !== '')
                    ? trim((string) $motivoRow->SIGLA)
                    : null;
                $ctxPayload = [
                    'escala_id' => $escalaId,
                    'detalhe_escala_id' => $detalheId,
                    'funcionario_id' => $funcionarioId,
                    'data' => $dataEscala,
                    'turno_anterior' => $itemAtual
                        ? ($itemAtual->TURNO_SIGLA_JOIN ?? ($temTurnoSiglaNoItem ? ($itemAtual->TURNO_SIGLA ?? null) : null))
                        : null,
                    'turno_novo' => $isDeleteAction ? null : $turnoSigla,
                    'motivo_alteracao_id' => $motivoId,
                    'motivo_sigla' => $siglaAudit,
                    'motivo_titulo' => $motivoRow ? MotivoAlteracaoPolicy::tituloParaExibicao($motivoRow) : null,
                    'base_legal' => $metaAudit['base_legal'],
                    'impacto_financeiro' => $metaAudit['impacto_financeiro'],
                    'documento_referencia' => $docRef !== '' ? $docRef : null,
                    'observacao_livre' => $obsLivre !== '' ? $obsLivre : null,
                    'observacao_adicional' => $obsAdic !== '' ? $obsAdic : null,
                    'texto_justificativa' => $observacaoEfetiva !== '' ? $observacaoEfetiva : null,
                ];
                if ($gradeEditBypass) {
                    $ctxPayload['intervencao_sudo_grade'] = true;
                    $ctxPayload['escala_status_no_evento'] = $stNorm;
                    $ctxPayload['competencia'] = $competencia;
                    $ctxPayload['setor_id'] = $setorId;
                    $ctxPayload['operacao'] = $isDeleteAction ? 'delete' : 'upsert';
                    $uAudit = auth()->user();
                    if ($uAudit && (int) ($uAudit->USUARIO_ID ?? 0) > 0) {
                        $resolverAudit = new RbacResolver();
                        $aidGrade = $resolverAudit->assignmentIdParaIntervencaoGrade((int) $uAudit->USUARIO_ID, $request);
                        if ($aidGrade !== null) {
                            $ctxPayload['gente_assignment_id'] = $aidGrade;
                            $roleSlug = DB::table('GENTE_ASSIGNMENT as a')
                                ->join('GENTE_ROLE as r', 'r.GENTE_ROLE_ID', '=', 'a.GENTE_ROLE_ID')
                                ->where('a.GENTE_ASSIGNMENT_ID', $aidGrade)
                                ->value('r.ROLE_SLUG');
                            if ($roleSlug !== null) {
                                $ctxPayload['gente_role_slug'] = (string) $roleSlug;
                            }
                        }
                    }
                }
                $ctxJson = json_encode($ctxPayload, JSON_UNESCAPED_UNICODE);

                $auditAcao = $gradeEditBypass
                    ? EscalaWorkflowService::ACAO_INTERVENCAO_TECNICA_GRADE
                    : ($isDeleteAction ? 'ESCALA_ITEM_DELETE' : 'ESCALA_ITEM_UPSERT');

                $auditData = [];
                if ($c = $pickCol('ACAO', 'acao')) {
                    $auditData[$c] = $auditAcao;
                }
                if ($c = $pickCol('TABELA', 'tabela')) {
                    $auditData[$c] = 'DETALHE_ESCALA_ITEM';
                }
                if ($c = $pickCol('DADOS_NOVOS', 'dados_novos', 'dados', 'contexto', 'context')) {
                    $auditData[$c] = $ctxJson;
                } elseif ($c = $pickCol('DADOS_ANTIGOS', 'dados_antigos')) {
                    $auditData[$c] = $ctxJson;
                }
                if ($c = $pickCol('USUARIO_ID', 'usuario_id', 'USER_ID', 'user_id')) {
                    $auditData[$c] = (int) GenteAuditWriter::requireAuthenticatedUserId();
                }
                if ($c = $pickCol('IP')) {
                    $auditData[$c] = (string) $request->ip();
                }
                if ($c = $pickCol('USER_AGENT', 'user_agent')) {
                    $auditData[$c] = Str::limit((string) $request->userAgent(), 255, '');
                }
                if ($c = $pickCol('evento', 'EVENTO', 'event_type')) {
                    $auditData[$c] = $gradeEditBypass ? 'INTERVENCAO_SUDO_GRADE' : 'ESCALA_TRABALHO';
                }
                if ($c = $pickCol('created_at', 'CREATED_AT', 'DATA_HORA')) {
                    $auditData[$c] = now();
                }
                if ($c = $pickCol('updated_at', 'UPDATED_AT')) {
                    $auditData[$c] = now();
                }
                if (!empty($auditData)) {
                    GenteAuditWriter::insertChainedRow($auditData);
                }
            }

            if ($pccvExcecao !== null && isset($pccvExcecao['v'], $pccvExcecao['texto'])) {
                GentePccvComplianceAudit::excecaoEscala(
                    $request,
                    $pccvExcecao['v'],
                    $escalaId,
                    $funcionarioId,
                    $dataEscala,
                    $pccvExcecao['texto']
                );
            }

            return ['escala_id' => $escalaId, 'detalhe_id' => $detalheId];
        });

        return response()->json(['ok' => true] + $payload, 201);
    } catch (AuthenticationException $e) {
        return response()->json(['ok' => false, 'erro' => $e->getMessage()], 401);
    } catch (\Illuminate\Http\Exceptions\HttpResponseException $e) {
        return $e->getResponse();
    } catch (\Throwable $e) {
        return response()->json(['ok' => false, 'erro' => $e->getMessage()], 422);
    }
});

Route::post('/escala-trabalho/workflow', function (\Illuminate\Http\Request $request) {
    $acoes = implode(',', [
        EscalaWorkflowService::ACAO_ENVIAR,
        EscalaWorkflowService::ACAO_REENVIAR,
        EscalaWorkflowService::ACAO_DEVOLVER,
        EscalaWorkflowService::ACAO_HOMOLOGAR,
    ]);
    $request->validate([
        'mes' => 'required|integer|min:1|max:12',
        'ano' => 'required|integer|min:2000|max:2100',
        'setor_id' => 'required|integer|min:1',
        'acao' => 'required|string|in:'.$acoes,
        'motivo_devolucao' => 'nullable|string|max:2000',
    ]);

    try {
        GenteAuditWriter::requireAuthenticatedUserId();
        $comp = sprintf('%04d-%02d', (int) $request->ano, (int) $request->mes);
        $setorWf = (int) $request->setor_id;
        $res = EscalaWorkflowService::processarTransicao(
            $request,
            $comp,
            $setorWf,
            (string) $request->input('acao'),
            $request->has('motivo_devolucao') ? (string) $request->input('motivo_devolucao') : null
        );

        return response()->json($res);
    } catch (AuthenticationException $e) {
        return response()->json(['ok' => false, 'erro' => $e->getMessage()], 401);
    } catch (\Throwable $e) {
        return response()->json(['ok' => false, 'erro' => $e->getMessage()], 422);
    }
});

Route::post('/escala-trabalho/copiar-mes-anterior', function (\Illuminate\Http\Request $request) {
    $request->validate([
        'mes' => 'required|integer|min:1|max:12',
        'ano' => 'required|integer|min:2000|max:2100',
        'setor_id' => 'nullable|integer',
    ]);

    try {
        GenteAuditWriter::requireAuthenticatedUserId();
        $mes = (int) $request->mes;
        $ano = (int) $request->ano;
        $setorId = $request->filled('setor_id') ? (int) $request->setor_id : null;
        $atual = sprintf('%04d-%02d', $ano, $mes);
        $ref = \Carbon\Carbon::create($ano, $mes, 1)->subMonth();
        $anterior = $ref->format('Y-m');
        $diasDestino = \Carbon\Carbon::create($ano, $mes, 1)->daysInMonth;

        $escalaOrigem = DB::table('ESCALA')
            ->where('ESCALA_COMPETENCIA', $anterior)
            ->when($setorId, fn ($q) => $q->where('SETOR_ID', $setorId))
            ->first();
        if (!$escalaOrigem) {
            return response()->json(['ok' => false, 'erro' => "Não existe escala em {$anterior} para copiar."], 422);
        }
        $setorEfeito = (int) ($setorId ?: ($escalaOrigem->SETOR_ID ?? 0));
        if ($setorEfeito > 0) {
            UnidadeEscopoUsuario::abortoSeSetorNaoAutorizado(auth()->user(), $setorEfeito, $request);
        }

        $payload = DB::transaction(function () use ($request, $escalaOrigem, $setorId, $atual, $diasDestino, $ano, $mes, $anterior) {
            $escalaDestino = DB::table('ESCALA')
                ->where('ESCALA_COMPETENCIA', $atual)
                ->when($setorId, fn ($q) => $q->where('SETOR_ID', $setorId))
                ->first();
            if (!$escalaDestino) {
                $insert = ['ESCALA_COMPETENCIA' => $atual, 'SETOR_ID' => $setorId ?: (int) ($escalaOrigem->SETOR_ID ?? 0)];
                if (\Illuminate\Support\Facades\Schema::hasColumn('ESCALA', 'ESCALA_ATIVO')) {
                    $insert['ESCALA_ATIVO'] = 1;
                }
                if (\Illuminate\Support\Facades\Schema::hasColumn('ESCALA', 'ESCALA_OBSERVACAO')) {
                    $insert['ESCALA_OBSERVACAO'] = "Copiada automaticamente de {$anterior}";
                }
                if (Schema::hasColumn('ESCALA', 'ESCALA_STATUS')) {
                    $insert['ESCALA_STATUS'] = EscalaWorkflowStatus::RASCUNHO;
                }
                if (Schema::hasColumn('ESCALA', 'created_at')) {
                    $insert['created_at'] = now();
                }
                if (Schema::hasColumn('ESCALA', 'updated_at')) {
                    $insert['updated_at'] = now();
                }
                $escalaDestinoId = DB::table('ESCALA')->insertGetId($insert);
            } else {
                $escalaDestinoId = $escalaDestino->ESCALA_ID;
            }

            $destCab = DB::table('ESCALA')->where('ESCALA_ID', $escalaDestinoId)->first();
            EscalaWorkflowService::assertPodeEditarGrade(EscalaWorkflowService::rowToArray($destCab), $request);

            $detalhesOrigem = DB::table('DETALHE_ESCALA')
                ->where('ESCALA_ID', $escalaOrigem->ESCALA_ID)
                ->get(['DETALHE_ESCALA_ID', 'FUNCIONARIO_ID']);

            $mapDetalhes = [];
            foreach ($detalhesOrigem as $de) {
                $dest = DB::table('DETALHE_ESCALA')
                    ->where('ESCALA_ID', $escalaDestinoId)
                    ->where('FUNCIONARIO_ID', $de->FUNCIONARIO_ID)
                    ->first();
                if (!$dest) {
                    $destId = DB::table('DETALHE_ESCALA')->insertGetId([
                        'ESCALA_ID' => $escalaDestinoId,
                        'FUNCIONARIO_ID' => $de->FUNCIONARIO_ID,
                    ]);
                } else {
                    $destId = $dest->DETALHE_ESCALA_ID;
                }
                $mapDetalhes[(int) $de->DETALHE_ESCALA_ID] = (int) $destId;
            }

            $itensCopiados = 0;
            $temSigla = \Illuminate\Support\Facades\Schema::hasColumn('DETALHE_ESCALA_ITEM', 'TURNO_SIGLA');
            $temObs = \Illuminate\Support\Facades\Schema::hasColumn('DETALHE_ESCALA_ITEM', 'DETALHE_ESCALA_ITEM_OBS');
            $temUpdatedAtItemCopy = \Illuminate\Support\Facades\Schema::hasColumn('DETALHE_ESCALA_ITEM', 'updated_at');
            $itensOrigem = DB::table('DETALHE_ESCALA_ITEM')
                ->whereIn('DETALHE_ESCALA_ID', array_keys($mapDetalhes))
                ->get();
            foreach ($itensOrigem as $item) {
                $d = \Carbon\Carbon::parse($item->DETALHE_ESCALA_ITEM_DATA)->day;
                if ($d > $diasDestino) continue;
                $novaData = sprintf('%04d-%02d-%02d', $ano, $mes, $d);
                $payloadItem = ['TURNO_ID' => $item->TURNO_ID];
                if ($temUpdatedAtItemCopy) {
                    $payloadItem['updated_at'] = now();
                }
                if ($temSigla && property_exists($item, 'TURNO_SIGLA')) $payloadItem['TURNO_SIGLA'] = $item->TURNO_SIGLA;
                if ($temObs && property_exists($item, 'DETALHE_ESCALA_ITEM_OBS')) $payloadItem['DETALHE_ESCALA_ITEM_OBS'] = $item->DETALHE_ESCALA_ITEM_OBS;
                DB::table('DETALHE_ESCALA_ITEM')->updateOrInsert(
                    ['DETALHE_ESCALA_ID' => $mapDetalhes[(int) $item->DETALHE_ESCALA_ID], 'DETALHE_ESCALA_ITEM_DATA' => $novaData],
                    $payloadItem
                );
                $itensCopiados++;
            }

            return ['escala_id' => $escalaDestinoId, 'itens_copiados' => $itensCopiados];
        });

        return response()->json(['ok' => true] + $payload);
    } catch (AuthenticationException $e) {
        return response()->json(['ok' => false, 'erro' => $e->getMessage()], 401);
    } catch (\Illuminate\Http\Exceptions\HttpResponseException $e) {
        return $e->getResponse();
    } catch (\Throwable $e) {
        return response()->json(['ok' => false, 'erro' => $e->getMessage()], 422);
    }
});
});

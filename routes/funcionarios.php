<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use App\Models\Funcionario;
use App\Models\DetalheFolha;
use App\Support\UnidadeEscopoUsuario;

// Herda api/v3 + web + auth + audit do grupo em routes/web.php

// â”€â”€ FuncionÃ¡rios (listagem paginada) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
Route::get('/funcionarios', function (\Illuminate\Http\Request $request) {
    $hoje = now()->toDateString();
    $limiteVinculo = now()->addDays(30)->toDateString();
    $auditFilter = trim((string) $request->input('audit_filter', ''));
    $maskCpf = function ($cpf) {
        $d = preg_replace('/\D+/', '', (string) ($cpf ?? ''));
        if (strlen($d) !== 11) {
            return $cpf;
        }
        return substr($d, 0, 3) . '.***.***-' . substr($d, -2);
    };
    $query = \App\Models\Funcionario::with(['pessoa', 'lotacoes.setor.unidade', 'lotacoes.vinculo'])
        ->when(
            $request->q,
            fn($q, $busca) =>
            $q->whereHas('pessoa', fn($p) => $p->where('PESSOA_NOME', 'like', "%{$busca}%"))
                ->orWhere('FUNCIONARIO_MATRICULA', 'like', "%{$busca}%")
        );

    $permitidosSetor = UnidadeEscopoUsuario::setorIdsPermitidos(auth()->user(), $request);
    if ($permitidosSetor === []) {
        $paginado = $query->whereRaw('0 = 1')->paginate(15);

        return response()->json(array_merge($paginado->toArray(), [
            'total_ativos' => 0,
            'total_geral' => 0,
            'limbo_total' => 0,
            'limbo_custo_mensal_estimado' => 0.0,
        ]));
    }
    if ($permitidosSetor !== null && $auditFilter !== 'limbo') {
        if ($request->funcionario_ativo === '0') {
            $query->lotacaoVigenteEmSetores($permitidosSetor, $hoje);
        } else {
            $query->ativosNoEscopo($permitidosSetor, $hoje);
        }
    } elseif ($permitidosSetor !== null) {
        $query->lotacaoVigenteEmSetores($permitidosSetor, $hoje);
    }

    if ($request->funcionario_ativo === '1') {
        $query->where(function ($w) use ($hoje) {
            $w->whereNull('FUNCIONARIO_DATA_FIM')
                ->orWhere('FUNCIONARIO_DATA_FIM', '>', $hoje);
        });
    } elseif ($request->funcionario_ativo === '0') {
        $query->whereNotNull('FUNCIONARIO_DATA_FIM')
            ->where('FUNCIONARIO_DATA_FIM', '<=', $hoje);
    }

    if ($auditFilter === 'limbo') {
        $query->where(function ($w) use ($hoje) {
            $w->whereNull('FUNCIONARIO_DATA_FIM')
                ->orWhere('FUNCIONARIO_DATA_FIM', '>', $hoje);
        })->whereDoesntHave('lotacoes', function ($q) {
            $q->whereNull('LOTACAO_DATA_FIM');
        });
    } elseif ($auditFilter === 'vinculo_expirando') {
        $query->whereBetween('FUNCIONARIO_DATA_FIM', [$hoje, $limiteVinculo])
            ->whereHas('lotacoes.vinculo', function ($q) {
                $q->where(function ($w) {
                    $w->where('VINCULO_NOME', 'like', '%TEMP%')
                        ->orWhere('VINCULO_NOME', 'like', '%SELETIV%')
                        ->orWhere('VINCULO_NOME', 'like', '%CONTRAT%');
                });
            });
    } elseif ($auditFilter === 'progressao_pendente') {
        if (\Illuminate\Support\Facades\Schema::hasTable('PROGRESSAO') && \Illuminate\Support\Facades\Schema::hasColumn('PROGRESSAO', 'FUNCIONARIO_ID')) {
            $query->whereDate('FUNCIONARIO_DATA_INICIO', '<=', now()->subYears(2)->toDateString())
                ->whereNotExists(function ($sq) {
                    $sq->select(DB::raw(1))
                        ->from('PROGRESSAO as p')
                        ->whereColumn('p.FUNCIONARIO_ID', 'FUNCIONARIO.FUNCIONARIO_ID');
                });
        } else {
            $query->whereRaw('1 = 0');
        }
    }

    $paginado = $query->orderBy('FUNCIONARIO_ID')->paginate(15);

    $paginado->getCollection()->transform(function ($f) use ($maskCpf, $hoje) {
        $lotacao = $f->lotacoes
            ->filter(fn($l) => empty($l->LOTACAO_DATA_FIM) || $l->LOTACAO_DATA_FIM > $hoje)
            ->sortByDesc('LOTACAO_ID')
            ->first();
        $ativo = empty($f->FUNCIONARIO_DATA_FIM) || $f->FUNCIONARIO_DATA_FIM > $hoje;
        $setorNome = optional(optional($lotacao)->setor)->SETOR_NOME;
        $unidadeNome = optional(optional(optional($lotacao)->setor)->unidade)->UNIDADE_NOME;
        $emLimbo = $ativo && !$setorNome;
        $afastado = false;
        if ($ativo && Schema::hasTable('AFASTAMENTO')) {
            $afastado = DB::table('AFASTAMENTO')
                ->where('FUNCIONARIO_ID', $f->FUNCIONARIO_ID)
                ->where(function ($q) {
                    if (Schema::hasColumn('AFASTAMENTO', 'AFASTAMENTO_STATUS')) {
                        $q->whereIn('AFASTAMENTO_STATUS', ['APROVADO', 'VALIDADO', 'aprovado']);
                    }
                })
                ->whereDate('AFASTAMENTO_DATA_INICIO', '<=', now()->toDateString())
                ->whereDate('AFASTAMENTO_DATA_FIM', '>=', now()->toDateString())
                ->exists();
        }
        $statusSemantico = $afastado
            ? ['tipo' => 'afastado', 'label' => 'Afastado', 'icone' => '🔴', 'tooltip' => 'Servidor em licença/afastamento ativo']
            : ($emLimbo
                ? ['tipo' => 'limbo', 'label' => 'Disponível (Limbo)', 'icone' => '🟡', 'tooltip' => 'Servidor sem lotação definida']
                : ($ativo
                    ? ['tipo' => 'lotado', 'label' => 'Lotado', 'icone' => '🟢', 'tooltip' => $setorNome ? "Lotado em {$setorNome}" : 'Lotação ativa']
                    : ['tipo' => 'inativo', 'label' => 'Inativo', 'icone' => '⚫', 'tooltip' => 'Cadastro inativo']));
        $arr = $f->toArray();
        if (!empty($arr['pessoa']['PESSOA_CPF_NUMERO'])) {
            $arr['pessoa']['PESSOA_CPF_NUMERO'] = $maskCpf($arr['pessoa']['PESSOA_CPF_NUMERO']);
        }
        return array_merge($f->toArray(), [
            'pessoa' => $arr['pessoa'] ?? null,
            'setor' => $setorNome,
            'unidade' => $unidadeNome,
            'vinculo' => optional(optional($lotacao)->vinculo)->VINCULO_NOME,
            'atribuicao' => null,
            'status_semantico' => $statusSemantico,
            'em_limbo' => $emLimbo,
            'custo_mensal_estimado' => isset($f->cargo->CARGO_SALARIO) ? (float) $f->cargo->CARGO_SALARIO : null,
        ]);
    });

    $totalAtivos = (int) Funcionario::query()->ativosNoEscopo($permitidosSetor, $hoje)->count();

    $totalGeralEscopo = (int) ($permitidosSetor === null
        ? Funcionario::query()->count()
        : Funcionario::query()->lotacaoVigenteEmSetores($permitidosSetor, $hoje)->count());

    if ($permitidosSetor !== null) {
        $limboTotal = 0;
        $limboCusto = 0.0;
    } else {
        $limboTotal = (int) \Illuminate\Support\Facades\DB::table('FUNCIONARIO as f')
            ->leftJoin('LOTACAO as l', function ($j) {
                $j->on('l.FUNCIONARIO_ID', '=', 'f.FUNCIONARIO_ID')
                    ->whereNull('l.LOTACAO_DATA_FIM');
            })
            ->where(function ($w) use ($hoje) {
                $w->whereNull('f.FUNCIONARIO_DATA_FIM')
                    ->orWhere('f.FUNCIONARIO_DATA_FIM', '>', $hoje);
            })
            ->whereNull('l.LOTACAO_ID')
            ->count();
        $limboCusto = (float) \Illuminate\Support\Facades\DB::table('FUNCIONARIO as f')
            ->leftJoin('LOTACAO as l', function ($j) {
                $j->on('l.FUNCIONARIO_ID', '=', 'f.FUNCIONARIO_ID')
                    ->whereNull('l.LOTACAO_DATA_FIM');
            })
            ->leftJoin('CARGO as c', 'c.CARGO_ID', '=', 'f.CARGO_ID')
            ->where(function ($w) use ($hoje) {
                $w->whereNull('f.FUNCIONARIO_DATA_FIM')
                    ->orWhere('f.FUNCIONARIO_DATA_FIM', '>', $hoje);
            })
            ->whereNull('l.LOTACAO_ID')
            ->sum(DB::raw('COALESCE(c.CARGO_SALARIO, 0)'));
    }

    $resp = $paginado->toArray();
    $resp['total_ativos'] = $totalAtivos;
    $resp['total_geral'] = $totalGeralEscopo;
    $resp['limbo_total'] = $limboTotal;
    $resp['limbo_custo_mensal_estimado'] = $limboCusto;
    return response()->json($resp);
});

// â”€â”€ Perfil de um funcionÃ¡rio â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
Route::get('/funcionarios/{id}', function ($id, \Illuminate\Http\Request $request) {
    $f = \App\Models\Funcionario::with(['pessoa', 'lotacoes.setor', 'lotacoes.vinculo', 'lotacoes.atribuicaoLotacoes.atribuicao'])
        ->findOrFail($id);
    $u = auth()->user();
    if ($u && UnidadeEscopoUsuario::setorIdsPermitidos($u, $request) !== null && ! $u->podeGerenciar($f, $request)) {
        return response()->json(['erro' => 'Funcionário fora do seu escopo hierárquico de setores.'], 403);
    }
    $ultimaLotacao = $f->lotacoes->sortByDesc('LOTACAO_ID')->first();
    $detalhes = \App\Models\DetalheFolha::with('folha')
        ->where('FUNCIONARIO_ID', $id)
        ->orderByDesc('FOLHA_ID')->take(6)->get()
        ->map(fn($d) => [
            'detalhe_folha_id' => $d->DETALHE_FOLHA_ID,
            'folha_id' => $d->FOLHA_ID,
            'funcionario_id' => $d->FUNCIONARIO_ID,
            'competencia' => optional($d->folha)->FOLHA_COMPETENCIA,
            'proventos' => (float) ($d->DETALHE_FOLHA_PROVENTOS ?? 0),
            'descontos' => (float) ($d->DETALHE_FOLHA_DESCONTOS ?? 0),
            'liquido' => (float) ($d->DETALHE_FOLHA_LIQUIDO ?? 0),
        ]);

    // Busca o e-mail do usuário vinculado ao funcionário
    $usuario = null;
    if (!empty($f->USUARIO_ID)) {
        $usuario = \App\Models\Usuario::find($f->USUARIO_ID);
    } elseif (!empty(optional($f->pessoa)->PESSOA_CPF_NUMERO)) {
        $usuario = \App\Models\Usuario::where('USUARIO_CPF', $f->pessoa->PESSOA_CPF_NUMERO)->first();
    }

    $funcionarioArray = [
        'FUNCIONARIO_ID' => $f->FUNCIONARIO_ID,
        'PESSOA_ID' => $f->PESSOA_ID,
        'USUARIO_ID' => $f->USUARIO_ID,
        'FUNCIONARIO_MATRICULA' => $f->FUNCIONARIO_MATRICULA,
        'FUNCIONARIO_DATA_INICIO' => $f->FUNCIONARIO_DATA_INICIO,
        'FUNCIONARIO_DATA_FIM' => $f->FUNCIONARIO_DATA_FIM,
        'FUNCIONARIO_OBSERVACAO' => $f->FUNCIONARIO_OBSERVACAO,
        'email' => $usuario?->USUARIO_EMAIL ?? null,
        'pessoa' => $f->pessoa ? [
            'PESSOA_NOME' => $f->pessoa->PESSOA_NOME,
            'PESSOA_CPF_NUMERO' => $f->pessoa->PESSOA_CPF_NUMERO,
            'PESSOA_DATA_NASCIMENTO' => $f->pessoa->PESSOA_DATA_NASCIMENTO ?? null,
        ] : null,
    ];

    return response()->json([
        'funcionario' => $funcionarioArray,
        'lotacao' => $ultimaLotacao,
        'holerites' => $detalhes,
    ]);
});

// â”€â”€ Dados de apoio (selectboxes do modal) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
Route::get('/apoio', function () {
    $setores = \App\Models\Setor::with('unidade')
        ->orderBy('SETOR_NOME')->get()
        ->map(fn($s) => [
            'id' => $s->SETOR_ID,
            'nome' => $s->SETOR_NOME,
            'unidade' => optional($s->unidade)->UNIDADE_NOME,
        ]);

    $vinculos = \App\Models\Vinculo::orderBy('VINCULO_NOME')->get()
        ->map(fn($v) => ['id' => $v->VINCULO_ID, 'nome' => $v->VINCULO_NOME]);

    $atribuicoes = \Illuminate\Support\Facades\DB::table('ATRIBUICAO')
        ->whereNull('ATRIBUICAO_DATA_EXCLUSAO')
        ->where(function ($q) {
            $q->whereNull('ATRIBUICAO_ATIVO')->orWhere('ATRIBUICAO_ATIVO', 1);
        })
        ->orderBy('ATRIBUICAO_NOME')
        ->select('ATRIBUICAO_ID as id', 'ATRIBUICAO_NOME as nome')
        ->get();

    $cargos = \Illuminate\Support\Facades\DB::table('CARGO')
        ->where(function ($q) {
            $q->whereNull('CARGO_ATIVO')->orWhere('CARGO_ATIVO', 1);
        })
        ->orderBy('CARGO_NOME')
        ->select('CARGO_ID as id', 'CARGO_NOME as nome', 'CARGO_SALARIO as salario', 'CARGO_CARGA_HORARIA as carga_horaria')
        ->get();

    return response()->json(compact('setores', 'vinculos', 'atribuicoes', 'cargos'));
});

// â”€â”€ Criar novo funcionÃ¡rio â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
Route::post('/funcionarios', function (\Illuminate\Http\Request $request) {
    \Illuminate\Support\Facades\DB::beginTransaction();
    try {
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'PESSOA_NOME' => 'required|string|min:3|max:150',
            'FUNCIONARIO_DATA_INICIO' => 'required|date',
            'CARGO_ID' => 'required|integer',
            'PESSOA_CPF_NUMERO' => ['nullable', 'regex:/^\d{3}\.?\d{3}\.?\d{3}\-?\d{2}$/'],
            'PESSOA_EMAIL' => 'nullable|email:rfc|max:150',
            'PESSOA_CELULAR' => 'nullable|string|max:20',
            'PESSOA_TELEFONE' => 'nullable|string|max:20',
        ], [
            'PESSOA_NOME.required' => 'Nome é obrigatório.',
            'FUNCIONARIO_DATA_INICIO.required' => 'Data de admissão é obrigatória.',
            'CARGO_ID.required' => 'Cargo é obrigatório para cadastro completo.',
            'PESSOA_CPF_NUMERO.regex' => 'CPF inválido.',
            'PESSOA_EMAIL.email' => 'E-mail inválido.',
        ]);
        if ($validator->fails()) {
            return response()->json(['erro' => $validator->errors()->first()], 422);
        }

        $cpfLimpo = preg_replace('/\D+/', '', (string) $request->input('PESSOA_CPF_NUMERO', ''));
        if ($cpfLimpo !== '') {
            $cpfFormatado = substr($cpfLimpo, 0, 3) . '.' . substr($cpfLimpo, 3, 3) . '.' . substr($cpfLimpo, 6, 3) . '-' . substr($cpfLimpo, 9, 2);
            $cpfExiste = \Illuminate\Support\Facades\DB::table('PESSOA')
                ->where('PESSOA_CPF_NUMERO', $cpfFormatado)
                ->exists();
            if ($cpfExiste) {
                return response()->json(['erro' => 'CPF já cadastrado na base.'], 422);
            }
        }

        $dadosPonto = $request->only([
            'REGIME',
            'HORA_ENTRADA',
            'HORA_SAIDA',
            'TOLERANCIA',
            'INTERVALO_ALMOCO',
        ]);
        $validatorPonto = \Illuminate\Support\Facades\Validator::make($dadosPonto, [
            'REGIME' => 'nullable|in:2_batidas,4_batidas',
            'HORA_ENTRADA' => ['nullable', 'regex:/^\d{2}:\d{2}$/'],
            'HORA_SAIDA' => ['nullable', 'regex:/^\d{2}:\d{2}$/'],
            'TOLERANCIA' => 'nullable|integer|min:0|max:120',
            'INTERVALO_ALMOCO' => 'nullable|integer|min:0|max:240',
        ], [
            'REGIME.in' => 'Regime inválido.',
            'HORA_ENTRADA.regex' => 'Hora de entrada inválida. Use HH:MM.',
            'HORA_SAIDA.regex' => 'Hora de saída inválida. Use HH:MM.',
        ]);
        if ($validatorPonto->fails()) {
            return response()->json(['erro' => $validatorPonto->errors()->first()], 422);
        }

        if ($request->filled('ATRIBUICAO_ID') && !$request->filled('SETOR_ID') && \Illuminate\Support\Facades\Schema::hasTable('ATRIBUICAO')) {
            $atrNome = (string) \Illuminate\Support\Facades\DB::table('ATRIBUICAO')
                ->where('ATRIBUICAO_ID', (int) $request->ATRIBUICAO_ID)
                ->value('ATRIBUICAO_NOME');
            if ($atrNome !== '' && str_contains(mb_strtoupper($atrNome, 'UTF-8'), 'REGEN')) {
                return response()->json([
                    'erro' => 'Blindagem fiscal: não é permitido atribuir gratificação de regência sem lotação ativa em setor.',
                ], 422);
            }
        }

        // 1. Cria a Pessoa
        $pessoa = new \App\Models\Pessoa();
        $pessoa->fill($request->only([
            'PESSOA_NOME',
            'PESSOA_CPF_NUMERO',
            'PESSOA_DATA_NASCIMENTO',
            'PESSOA_SEXO',
            'PESSOA_ESTADO_CIVIL',
            'PESSOA_ESCOLARIDADE',
            'PESSOA_NACIONALIDADE',
            'PESSOA_RACA',
            'PESSOA_GENERO',
            'PESSOA_PCD',
            'PESSOA_NOME_MAE',
            'PESSOA_NOME_PAI',
            'PESSOA_ENDERECO',
            'PESSOA_COMPLEMENTO',
            'PESSOA_CEP',
            'BAIRRO_ID',
            'CIDADE_ID',
            'PESSOA_RG_NUMERO',
            'PESSOA_RG_EXPEDIDOR',
            'PESSOA_RG_EXPEDICAO',
            'PESSOA_TITULO_NUMERO',
            'PESSOA_TITULO_ZONA',
            'PESSOA_TITULO_SECAO',
            'PESSOA_CNH_NUMERO',
            'PESSOA_CNH_CATEGORIA',
            'PESSOA_CNH_VALIDADE',
            'PESSOA_TIPO_SANGUE',
            'PESSOA_RH_MAIS',
        ]));
        $pessoa->PESSOA_DATA_CADASTRO = now()->toDateString();
        $pessoa->save();

        // Campos complementares com normalização para tipos numéricos
        $extraPessoa = [];
        $ufRaw = $request->input('UF_ID_RG');
        if ($ufRaw !== null && $ufRaw !== '') {
            if (is_numeric($ufRaw)) {
                $extraPessoa['UF_ID_RG'] = (int) $ufRaw;
            } else {
                $ufId = \Illuminate\Support\Facades\DB::table('UF')
                    ->whereRaw('UPPER(UF_SIGLA) = ?', [strtoupper((string) $ufRaw)])
                    ->value('UF_ID');
                if ($ufId) {
                    $extraPessoa['UF_ID_RG'] = (int) $ufId;
                }
            }
        }
        $cidadeNaturalRaw = $request->input('CIDADE_ID_NATURAL');
        if ($cidadeNaturalRaw !== null && $cidadeNaturalRaw !== '' && is_numeric($cidadeNaturalRaw)) {
            $extraPessoa['CIDADE_ID_NATURAL'] = (int) $cidadeNaturalRaw;
        }
        if (!empty($extraPessoa)) {
            \Illuminate\Support\Facades\DB::table('PESSOA')
                ->where('PESSOA_ID', $pessoa->PESSOA_ID)
                ->update($extraPessoa);
        }

        // 2. Cria o FuncionÃ¡rio
        $funcionario = new \App\Models\Funcionario();
        $funcionario->PESSOA_ID = $pessoa->PESSOA_ID;
        $funcionario->fill($request->only([
            'FUNCIONARIO_MATRICULA',
            'FUNCIONARIO_DATA_INICIO',
            'FUNCIONARIO_DATA_FIM',
            'FUNCIONARIO_TIPO_ENTRADA',
            'FUNCIONARIO_OBSERVACAO',
            'CARGO_ID',
        ]));
        $funcionario->FUNCIONARIO_DATA_INICIO = $request->FUNCIONARIO_DATA_INICIO ?? now()->toDateString();
        $funcionario->FUNCIONARIO_DATA_CADASTRO = now()->toDateString();

        // Gerar matrícula automática se não informada — padrão PMSL: ANO + sequencial 3 dígitos
        if (empty($funcionario->FUNCIONARIO_MATRICULA)) {
            $ano = now()->year;
            $ultimo = \Illuminate\Support\Facades\DB::table('FUNCIONARIO')
                ->where('FUNCIONARIO_MATRICULA', 'like', $ano . '%')
                ->orderByDesc('FUNCIONARIO_MATRICULA')
                ->value('FUNCIONARIO_MATRICULA');
            $seq = $ultimo ? ((int) substr((string) $ultimo, 4) + 1) : 1;
            $funcionario->FUNCIONARIO_MATRICULA = $ano . str_pad($seq, 3, '0', STR_PAD_LEFT);
        }

        $funcionario->save();

        if (\Illuminate\Support\Facades\Schema::hasTable('PONTO_CONFIG_FUNCIONARIO')) {
            \Illuminate\Support\Facades\DB::table('PONTO_CONFIG_FUNCIONARIO')->updateOrInsert(
                ['FUNCIONARIO_ID' => $funcionario->FUNCIONARIO_ID],
                [
                    'REGIME' => $dadosPonto['REGIME'] ?? '4_batidas',
                    'HORA_ENTRADA' => $dadosPonto['HORA_ENTRADA'] ?? '08:00',
                    'HORA_SAIDA' => $dadosPonto['HORA_SAIDA'] ?? '18:00',
                    'TOLERANCIA' => array_key_exists('TOLERANCIA', $dadosPonto) && $dadosPonto['TOLERANCIA'] !== null && $dadosPonto['TOLERANCIA'] !== ''
                        ? (int) $dadosPonto['TOLERANCIA']
                        : 15,
                    'INTERVALO_ALMOCO' => array_key_exists('INTERVALO_ALMOCO', $dadosPonto) && $dadosPonto['INTERVALO_ALMOCO'] !== ''
                        ? (int) $dadosPonto['INTERVALO_ALMOCO']
                        : null,
                    'updated_at' => now(),
                ]
            );
        }

        // 3. Cria a LotaÃ§Ã£o (se setor ou vÃ­nculo foi informado)
        if ($request->filled('SETOR_ID') || $request->filled('VINCULO_ID')) {
            $lotacao = new \App\Models\Lotacao();
            $lotacao->fill([
                'FUNCIONARIO_ID' => $funcionario->FUNCIONARIO_ID,
                'SETOR_ID' => $request->SETOR_ID,
                'VINCULO_ID' => $request->VINCULO_ID,
                'LOTACAO_DATA_INICIO' => $request->FUNCIONARIO_DATA_INICIO ?? now()->toDateString(),
            ]);
            $lotacao->save();

            // 4. Cria AtribuicaoLotacao se atribuiÃ§Ã£o foi informada
            if ($request->filled('ATRIBUICAO_ID')) {
                $atLotacao = new \App\Models\AtribuicaoLotacao([
                    'LOTACAO_ID' => $lotacao->LOTACAO_ID,
                    'ATRIBUICAO_ID' => $request->ATRIBUICAO_ID,
                ]);
                $atLotacao->save();
            }
        }

        // 5. Cria Contatos (telefone, celular, email) se informados
        $tiposContato = [
            'PESSOA_TELEFONE' => 1, // Telefone
            'PESSOA_EMAIL' => 2, // E-mail
            'PESSOA_CELULAR' => 3, // Celular
        ];
        $contatoCols = \Illuminate\Support\Facades\Schema::hasTable('CONTATO')
            ? \Illuminate\Support\Facades\Schema::getColumnListing('CONTATO')
            : [];
        foreach ($tiposContato as $campo => $tipo) {
            if ($request->filled($campo)) {
                $valorContato = (string) $request->$campo;
                if ($campo !== 'PESSOA_EMAIL') {
                    $valorContato = preg_replace('/\D+/', '', $valorContato);
                }
                $payloadContato = [];
                if (in_array('PESSOA_ID', $contatoCols, true)) {
                    $payloadContato['PESSOA_ID'] = $pessoa->PESSOA_ID;
                }
                if (in_array('CONTATO_TIPO', $contatoCols, true)) {
                    $payloadContato['CONTATO_TIPO'] = $tipo;
                } elseif (in_array('TIPO_CONTATO_ID', $contatoCols, true)) {
                    $payloadContato['TIPO_CONTATO_ID'] = $tipo;
                }
                if (in_array('CONTATO_CONTEUDO', $contatoCols, true)) {
                    $payloadContato['CONTATO_CONTEUDO'] = $valorContato;
                } elseif (in_array('CONTATO_VALOR', $contatoCols, true)) {
                    $payloadContato['CONTATO_VALOR'] = $valorContato;
                }
                if (!empty($payloadContato)) {
                    \Illuminate\Support\Facades\DB::table('CONTATO')->insert($payloadContato);
                }
            }
        }

        \Illuminate\Support\Facades\DB::commit();

        // 6. Criar usuário automaticamente se CPF informado
        // Login = CPF numérico, senha = CPF, forçar troca no primeiro acesso
        if ($cpfLimpo !== '') {
            try {
                $usuarioCols = \Illuminate\Support\Facades\Schema::getColumnListing('USUARIO');
                $loginExiste = \Illuminate\Support\Facades\DB::table('USUARIO')
                    ->where('USUARIO_LOGIN', $cpfLimpo)
                    ->exists();
                if (!$loginExiste) {
                    $payloadUsuario = [
                        'USUARIO_NOME'          => $request->PESSOA_NOME,
                        'USUARIO_LOGIN'         => $cpfLimpo,
                        'USUARIO_SENHA'         => bcrypt($cpfLimpo),
                        'USUARIO_EMAIL'         => $request->PESSOA_EMAIL ?? null,
                        'USUARIO_ATIVO'         => 1,
                        'USUARIO_ALTERAR_SENHA' => 1, // força troca no 1o acesso
                        'created_at'            => now(),
                        'updated_at'            => now(),
                    ];
                    // FUNCIONARIO_ID se a coluna existir
                    if (in_array('FUNCIONARIO_ID', $usuarioCols))
                        $payloadUsuario['FUNCIONARIO_ID'] = $funcionario->FUNCIONARIO_ID;
                    if (in_array('USUARIO_PRIMEIRO_ACESSO', $usuarioCols))
                        $payloadUsuario['USUARIO_PRIMEIRO_ACESSO'] = 1;

                    $payloadUsuario = array_intersect_key($payloadUsuario, array_flip($usuarioCols));
                    $usuarioId = \Illuminate\Support\Facades\DB::table('USUARIO')
                        ->insertGetId($payloadUsuario);

                    // Perfil padrão Funcionário
                    if (\Illuminate\Support\Facades\Schema::hasTable('USUARIO_PERFIL')) {
                        $upCols = \Illuminate\Support\Facades\Schema::getColumnListing('USUARIO_PERFIL');
                        $perfilId = \Illuminate\Support\Facades\DB::table('PERFIL')
                            ->whereIn('PERFIL_NOME', ['Funcionario', 'Funcionário', 'Externo'])
                            ->value('PERFIL_ID') ?? 5;
                        $payloadUp = [
                            'USUARIO_ID'           => $usuarioId,
                            'PERFIL_ID'            => $perfilId,
                            'USUARIO_PERFIL_ATIVO' => 1,
                            'created_at'           => now(),
                            'updated_at'           => now(),
                        ];
                        $payloadUp = array_intersect_key($payloadUp, array_flip($upCols));
                        \Illuminate\Support\Facades\DB::table('USUARIO_PERFIL')->insert($payloadUp);
                    }
                }
            } catch (\Throwable $eUser) {
                // Não bloquear cadastro do funcionário se criação de usuário falhar
                \Illuminate\Support\Facades\Log::warning('Falha ao criar usuário automático', [
                    'funcionario_id' => $funcionario->FUNCIONARIO_ID,
                    'erro' => $eUser->getMessage(),
                ]);
            }
        }

        return response()->json([
            'message' => 'FuncionÃ¡rio cadastrado com sucesso.',
            'funcionario_id' => $funcionario->FUNCIONARIO_ID,
            'pessoa_id' => $pessoa->PESSOA_ID,
        ], 201);

    } catch (\Throwable $e) {
        \Illuminate\Support\Facades\DB::rollBack();
        \Illuminate\Support\Facades\Log::error('Erro ao cadastrar funcionário', ['erro' => $e->getMessage()]);
        return response()->json(['erro' => 'Erro ao cadastrar funcionário. Revise os dados obrigatórios e tente novamente.'], 500);
    }
});

// â”€â”€ Atualizar funcionÃ¡rio (completo) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
Route::put('/funcionarios/{id}', function ($id, \Illuminate\Http\Request $request) {
    \Illuminate\Support\Facades\DB::beginTransaction();
    try {
        $f = \App\Models\Funcionario::with(['pessoa', 'lotacoes'])->findOrFail($id);
        $validatorBase = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'PESSOA_NOME' => 'nullable|string|min:3|max:150',
            'FUNCIONARIO_DATA_INICIO' => 'nullable|date',
            'PESSOA_CPF_NUMERO' => ['nullable', 'regex:/^\d{3}\.?\d{3}\.?\d{3}\-?\d{2}$/'],
            'PESSOA_EMAIL' => 'nullable|email:rfc|max:150',
        ], [
            'PESSOA_CPF_NUMERO.regex' => 'CPF inválido.',
            'PESSOA_EMAIL.email' => 'E-mail inválido.',
        ]);
        if ($validatorBase->fails()) {
            return response()->json(['erro' => $validatorBase->errors()->first()], 422);
        }

        $cpfEntrada = $request->input('PESSOA_CPF_NUMERO');
        if ($cpfEntrada && $f->pessoa) {
            $cpfLimpo = preg_replace('/\D+/', '', (string) $cpfEntrada);
            $cpfFormatado = substr($cpfLimpo, 0, 3) . '.' . substr($cpfLimpo, 3, 3) . '.' . substr($cpfLimpo, 6, 3) . '-' . substr($cpfLimpo, 9, 2);
            $cpfExiste = \Illuminate\Support\Facades\DB::table('PESSOA')
                ->where('PESSOA_CPF_NUMERO', $cpfFormatado)
                ->where('PESSOA_ID', '!=', $f->pessoa->PESSOA_ID)
                ->exists();
            if ($cpfExiste) {
                return response()->json(['erro' => 'CPF já cadastrado em outra pessoa.'], 422);
            }
        }

        $dadosPonto = $request->only([
            'REGIME',
            'HORA_ENTRADA',
            'HORA_SAIDA',
            'TOLERANCIA',
            'INTERVALO_ALMOCO',
        ]);
        $validatorPonto = \Illuminate\Support\Facades\Validator::make($dadosPonto, [
            'REGIME' => 'nullable|in:2_batidas,4_batidas',
            'HORA_ENTRADA' => ['nullable', 'regex:/^\d{2}:\d{2}$/'],
            'HORA_SAIDA' => ['nullable', 'regex:/^\d{2}:\d{2}$/'],
            'TOLERANCIA' => 'nullable|integer|min:0|max:120',
            'INTERVALO_ALMOCO' => 'nullable|integer|min:0|max:240',
        ], [
            'REGIME.in' => 'Regime inválido.',
            'HORA_ENTRADA.regex' => 'Hora de entrada inválida. Use HH:MM.',
            'HORA_SAIDA.regex' => 'Hora de saída inválida. Use HH:MM.',
        ]);
        if ($validatorPonto->fails()) {
            return response()->json(['erro' => $validatorPonto->errors()->first()], 422);
        }

        if ($request->filled('ATRIBUICAO_ID') && !$request->filled('SETOR_ID') && \Illuminate\Support\Facades\Schema::hasTable('ATRIBUICAO')) {
            $atrNome = (string) \Illuminate\Support\Facades\DB::table('ATRIBUICAO')
                ->where('ATRIBUICAO_ID', (int) $request->ATRIBUICAO_ID)
                ->value('ATRIBUICAO_NOME');
            if ($atrNome !== '' && str_contains(mb_strtoupper($atrNome, 'UTF-8'), 'REGEN')) {
                return response()->json([
                    'erro' => 'Blindagem fiscal: não é permitido atribuir gratificação de regência sem lotação ativa em setor.',
                ], 422);
            }
        }

        // Atualiza dados do FuncionÃ¡rio
        $f->fill($request->only([
            'FUNCIONARIO_MATRICULA',
            'FUNCIONARIO_DATA_INICIO',
            'FUNCIONARIO_DATA_FIM',
            'FUNCIONARIO_TIPO_ENTRADA',
            'FUNCIONARIO_TIPO_SAIDA',
            'FUNCIONARIO_OBSERVACAO',
            'CARGO_ID',
        ]));
        $f->FUNCIONARIO_DATA_ATUALIZACAO = now()->toDateString();
        $f->save();

        // Atualiza dados da Pessoa
        if ($f->pessoa) {
            // O Vue envia os campos de pessoa aninhados em { pessoa: { PESSOA_NOME: ..., ... } }
            $pessoaData = $request->input('pessoa', []);

            // Campos permitidos â€” mescla do sub-objeto com campos de raiz (retrocompatibilidade)
            $camposPermitidos = [
                'PESSOA_NOME',
                'PESSOA_CPF_NUMERO',
                'PESSOA_DATA_NASCIMENTO',
                'PESSOA_SEXO',
                'PESSOA_ESTADO_CIVIL',
                'PESSOA_ESCOLARIDADE',
                'PESSOA_NACIONALIDADE',
                'PESSOA_RACA',
                'PESSOA_GENERO',
                'PESSOA_PCD',
                'PESSOA_NOME_MAE',
                'PESSOA_NOME_PAI',
                'PESSOA_ENDERECO',
                'PESSOA_COMPLEMENTO',
                'PESSOA_CEP',
                'BAIRRO_ID',
                'CIDADE_ID',
                'PESSOA_RG_NUMERO',
                'PESSOA_RG_EXPEDIDOR',
                'PESSOA_RG_EXPEDICAO',
                'PESSOA_TITULO_NUMERO',
                'PESSOA_TITULO_ZONA',
                'PESSOA_TITULO_SECAO',
                'PESSOA_CNH_NUMERO',
                'PESSOA_CNH_CATEGORIA',
                'PESSOA_CNH_VALIDADE',
                'PESSOA_TIPO_SANGUE',
                'PESSOA_RH_MAIS',
                'PESSOA_PIS_PASEP',
            ];

            $dadosPessoa = [];
            foreach ($camposPermitidos as $campo) {
                if (array_key_exists($campo, $pessoaData)) {
                    $dadosPessoa[$campo] = $pessoaData[$campo];
                } elseif ($request->has($campo)) {
                    $dadosPessoa[$campo] = $request->input($campo);
                }
            }

            $f->pessoa->fill($dadosPessoa);
            $f->pessoa->save();


            // Campos que precisam ser salvos como texto (sigla UF e nome de municÃ­pio)
            $extraPessoa = [];
            $ufRg = $pessoaData['UF_ID_RG'] ?? $request->input('UF_ID_RG');
            $cidadeNatural = $pessoaData['CIDADE_ID_NATURAL'] ?? $request->input('CIDADE_ID_NATURAL');
            if (!empty($ufRg)) {
                if (is_numeric($ufRg)) {
                    $extraPessoa['UF_ID_RG'] = (int) $ufRg;
                } else {
                    $ufId = \Illuminate\Support\Facades\DB::table('UF')
                        ->whereRaw('UPPER(UF_SIGLA) = ?', [strtoupper((string) $ufRg)])
                        ->value('UF_ID');
                    if ($ufId) {
                        $extraPessoa['UF_ID_RG'] = (int) $ufId;
                    }
                }
            }
            if (!empty($cidadeNatural) && is_numeric($cidadeNatural)) {
                $extraPessoa['CIDADE_ID_NATURAL'] = (int) $cidadeNatural;
            }
            if (!empty($extraPessoa)) {
                \Illuminate\Support\Facades\DB::table('PESSOA')
                    ->where('PESSOA_ID', $f->pessoa->PESSOA_ID)
                    ->update($extraPessoa);
            }
        }

        // Atualiza ou cria lotaÃ§Ã£o ativa
        $lotacaoAtiva = $f->lotacoes->where('LOTACAO_DATA_FIM', null)->sortByDesc('LOTACAO_ID')->first();
        if ($request->filled('SETOR_ID') || $request->filled('VINCULO_ID')) {
            if ($lotacaoAtiva) {
                $lotacaoAtiva->fill($request->only(['SETOR_ID', 'VINCULO_ID']));
                $lotacaoAtiva->save();
            } else {
                \App\Models\Lotacao::create([
                    'FUNCIONARIO_ID' => $f->FUNCIONARIO_ID,
                    'SETOR_ID' => $request->SETOR_ID,
                    'VINCULO_ID' => $request->VINCULO_ID,
                    'LOTACAO_DATA_INICIO' => $request->FUNCIONARIO_DATA_INICIO ?? now()->toDateString(),
                ]);
            }
        }

        // Atualiza e-mail do usuÃ¡rio vinculado
        if ($request->filled('email') || $request->filled('USUARIO_EMAIL')) {
            $novoEmail = $request->email ?? $request->USUARIO_EMAIL;
            \App\Models\Usuario::where('FUNCIONARIO_ID', $f->FUNCIONARIO_ID)
                ->update(['USUARIO_EMAIL' => $novoEmail]);
        }

        // Atualiza contatos da pessoa (telefone/celular/email) com schema-aware
        if ($f->pessoa && \Illuminate\Support\Facades\Schema::hasTable('CONTATO')) {
            $contatoCols = \Illuminate\Support\Facades\Schema::getColumnListing('CONTATO');
            $campoTipo = in_array('TIPO_CONTATO_ID', $contatoCols, true) ? 'TIPO_CONTATO_ID' : (in_array('CONTATO_TIPO', $contatoCols, true) ? 'CONTATO_TIPO' : null);
            $campoValor = in_array('CONTATO_VALOR', $contatoCols, true) ? 'CONTATO_VALOR' : (in_array('CONTATO_CONTEUDO', $contatoCols, true) ? 'CONTATO_CONTEUDO' : null);
            if ($campoTipo && $campoValor) {
                $tiposContato = [
                    'PESSOA_TELEFONE' => 1,
                    'PESSOA_EMAIL' => 2,
                    'PESSOA_CELULAR' => 3,
                ];
                foreach ($tiposContato as $campoReq => $tipoContato) {
                    if (!$request->filled($campoReq)) {
                        continue;
                    }
                    $valorContato = (string) $request->input($campoReq);
                    if ($campoReq !== 'PESSOA_EMAIL') {
                        $valorContato = preg_replace('/\D+/', '', $valorContato);
                    } else {
                        $valorContato = strtolower(trim($valorContato));
                    }
                    \Illuminate\Support\Facades\DB::table('CONTATO')->updateOrInsert(
                        ['PESSOA_ID' => $f->pessoa->PESSOA_ID, $campoTipo => $tipoContato],
                        [$campoValor => $valorContato]
                    );
                }
            }
        }

        if (\Illuminate\Support\Facades\Schema::hasTable('PONTO_CONFIG_FUNCIONARIO')) {
            \Illuminate\Support\Facades\DB::table('PONTO_CONFIG_FUNCIONARIO')->updateOrInsert(
                ['FUNCIONARIO_ID' => $f->FUNCIONARIO_ID],
                [
                    'REGIME' => $dadosPonto['REGIME'] ?? '4_batidas',
                    'HORA_ENTRADA' => $dadosPonto['HORA_ENTRADA'] ?? '08:00',
                    'HORA_SAIDA' => $dadosPonto['HORA_SAIDA'] ?? '18:00',
                    'TOLERANCIA' => array_key_exists('TOLERANCIA', $dadosPonto) && $dadosPonto['TOLERANCIA'] !== null && $dadosPonto['TOLERANCIA'] !== ''
                        ? (int) $dadosPonto['TOLERANCIA']
                        : 15,
                    'INTERVALO_ALMOCO' => array_key_exists('INTERVALO_ALMOCO', $dadosPonto) && $dadosPonto['INTERVALO_ALMOCO'] !== ''
                        ? (int) $dadosPonto['INTERVALO_ALMOCO']
                        : null,
                    'updated_at' => now(),
                ]
            );
        }

        \Illuminate\Support\Facades\DB::commit();
        return response()->json(['message' => 'Atualizado com sucesso.']);
    } catch (\Throwable $e) {
        \Illuminate\Support\Facades\DB::rollBack();
        \Illuminate\Support\Facades\Log::error('Erro ao atualizar funcionário', ['id' => $id, 'erro' => $e->getMessage()]);
        return response()->json(['erro' => 'Erro ao atualizar funcionário. Verifique os dados e tente novamente.'], 500);
    }
});

Route::post('/funcionarios/{id}/resetar-senha', function ($id) {
    try {
        $user = \Illuminate\Support\Facades\Auth::user();
        // Só admin/RH pode resetar senha
        $perfil = strtolower(trim((string) ($user->PERFIL
            ?? optional($user->usuarioPerfis()->with('perfil')->first())->perfil->PERFIL_NOME
            ?? '')));
        if (!in_array($perfil, ['admin', 'rh', 'administrador', 'administracao'])) {
            return response()->json(['erro' => 'Sem permissão para resetar senha.'], 403);
        }

        $func = \App\Models\Funcionario::with('pessoa')->find($id);
        if (!$func) return response()->json(['erro' => 'Funcionário não encontrado.'], 404);

        // Buscar usuário pelo FUNCIONARIO_ID ou pelo CPF
        $cpf = preg_replace('/\D/', '', (string) ($func->pessoa->PESSOA_CPF_NUMERO ?? ''));
        $usuario = null;
        if (\Illuminate\Support\Facades\Schema::hasColumn('USUARIO', 'FUNCIONARIO_ID')) {
            $usuario = \App\Models\Usuario::where('FUNCIONARIO_ID', $id)->first();
        }
        if (!$usuario && $cpf) {
            $usuario = \App\Models\Usuario::where('USUARIO_LOGIN', $cpf)->first();
        }
        if (!$usuario) {
            return response()->json(['erro' => 'Usuário de acesso não encontrado para este funcionário.'], 404);
        }

        // Resetar para CPF, forçar troca no próximo login
        $novaSenha = $cpf ?: 'Mudar@123';
        $usuario->USUARIO_SENHA = bcrypt($novaSenha);
        $usuario->USUARIO_ALTERAR_SENHA = 1;
        if (\Illuminate\Support\Facades\Schema::hasColumn('USUARIO', 'USUARIO_PRIMEIRO_ACESSO')) {
            $usuario->USUARIO_PRIMEIRO_ACESSO = 1;
        }
        $usuario->save();

        \Illuminate\Support\Facades\Log::channel('security')->info('senha_resetada_admin', [
            'admin_id'      => $user->USUARIO_ID,
            'funcionario_id'=> $id,
            'usuario_id'    => $usuario->USUARIO_ID,
        ]);

        return response()->json([
            'ok'      => true,
            'message' => 'Senha resetada. O funcionário deverá trocar no próximo acesso.',
            'login'   => $usuario->USUARIO_LOGIN,
        ]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// ── Inativar funcionário (soft delete) ───────────────────────
Route::delete('/funcionarios/{id}', function ($id, \Illuminate\Http\Request $request) {
    $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
        'FUNCIONARIO_DATA_FIM' => 'nullable|date',
        'FUNCIONARIO_TIPO_SAIDA' => 'nullable|string|max:50',
    ]);
    if ($validator->fails()) {
        return response()->json(['erro' => $validator->errors()->first()], 422);
    }

    $f = \App\Models\Funcionario::findOrFail($id);
    $f->FUNCIONARIO_DATA_FIM = $request->FUNCIONARIO_DATA_FIM ?? now()->toDateString();
    $f->FUNCIONARIO_TIPO_SAIDA = $request->FUNCIONARIO_TIPO_SAIDA ?? null;
    $f->save();

    // Fecha lotações ativas para preservar histórico funcional.
    \App\Models\Lotacao::where('FUNCIONARIO_ID', $id)
        ->whereNull('LOTACAO_DATA_FIM')
        ->update(['LOTACAO_DATA_FIM' => $f->FUNCIONARIO_DATA_FIM]);

    return response()->json(['message' => 'Funcionário inativado com sucesso.']);
});

Route::patch('/funcionarios/{id}/reativar', function ($id) {
    $f = \App\Models\Funcionario::findOrFail($id);
    $f->FUNCIONARIO_DATA_FIM = null;
    $f->FUNCIONARIO_TIPO_SAIDA = null;
    $f->save();
    return response()->json(['ok' => true, 'message' => 'Funcionário reativado com sucesso.']);
});

// â”€â”€ Documentos â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
Route::get('/funcionarios/{id}/documentos', function ($id) {
    $f = \App\Models\Funcionario::findOrFail($id);
    $docs = \App\Models\Documento::with('tipoDocumento')
        ->where('PESSOA_ID', $f->PESSOA_ID)
        ->get()->map(fn($d) => [
            'tipo' => optional($d->tipoDocumento)->TIPO_DOCUMENTO_NOME,
            'numero' => $d->DOCUMENTO_NUMERO,
            'obrigatorio' => (bool) (optional($d->tipoDocumento)->TIPO_DOCUMENTO_OBRIGATORIO ?? false),
        ]);
    return response()->json($docs);
});

// â”€â”€ HistÃ³rico funcional â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
Route::get('/funcionarios/{id}/historico', function ($id) {
    $f = \App\Models\Funcionario::with([
        'lotacoes.setor',
        'lotacoes.atribuicaoLotacoes.atribuicao',
        'lotacoes.vinculo',
        'ferias',
        'afastamentos'
    ])->findOrFail($id);

    $lotacoes = $f->lotacoes->map(fn($l) => [
        'tipo' => 'lotacao',
        'data' => $l->LOTACAO_DATA_INICIO,
        'fim' => $l->LOTACAO_DATA_FIM,
        'label' => optional($l->setor)->SETOR_NOME,
        'extra' => optional($l->atribuicaoLotacoes?->first()?->atribuicao)->ATRIBUICAO_NOME,
        'ativa' => !$l->LOTACAO_DATA_FIM,
    ]);
    $ferias = $f->ferias->map(fn($v) => [
        'tipo' => 'ferias',
        'data' => $v->FERIAS_DATA_INICIO,
        'fim' => $v->FERIAS_DATA_FIM,
        'label' => 'FÃ©rias',
        'extra' => null,
    ]);
    $afastamentos = $f->afastamentos->map(fn($a) => [
        'tipo' => 'afastamento',
        'data' => $a->AFASTAMENTO_DATA_INICIO,
        'fim' => $a->AFASTAMENTO_DATA_FIM,
        'label' => $a->AFASTAMENTO_MOTIVO ?? 'Afastamento',
        'extra' => null,
    ]);

    $historico = $lotacoes->concat($ferias)->concat($afastamentos)
        ->sortByDesc('data')->values();
    return response()->json([
        'lotacoes' => $lotacoes->values(),
        'ferias' => $ferias->values(),
        'afastamentos' => $afastamentos->values(),
    ]);
});

// â”€â”€ Escalas â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
Route::get('/funcionarios/{id}/escalas', function ($id) {
    $itens = \App\Models\DetalheEscala::with(['escala', 'detalheEscalaItens.turno'])
        ->where('FUNCIONARIO_ID', $id)
        ->get()
        ->flatMap(fn($de) => $de->detalheEscalaItens->map(fn($item) => [
            'data' => $item->DETALHE_ESCALA_ITEM_DATA ?? optional($de->escala)->ESCALA_COMPETENCIA,
            'setor' => null,
            'turno' => optional($item->turno)->TURNO_DESCRICAO ?? null,
            'entrada' => optional($item->turno)->TURNO_HORA_INICIO ?? null,
            'saida' => optional($item->turno)->TURNO_HORA_FIM ?? null,
        ]))
        ->sortByDesc('data')
        ->take(60)
        ->values();
    return response()->json($itens);
});

// GET /api/v3/ponto — canónico em ponto_mes_spa_get.php (grupo [web] api_v3_web_part1)

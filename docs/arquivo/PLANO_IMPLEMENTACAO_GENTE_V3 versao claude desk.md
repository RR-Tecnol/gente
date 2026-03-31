# GENTE v3 — Plano de Implementação Completo
**Gerado:** 10/03/2026 | **Revisão:** varredura total de todos os arquivos PHP e Vue  
**Destino:** colar no Antigravity (Claude Opus) para execução  

---

## SUMÁRIO DE CRITICIDADE

| Categoria | Qtd | Impacto |
|-----------|-----|---------|
| Bugs críticos (quebram funcionalidade) | 6 | 🔴 Alto |
| Gaps de backend (endpoints faltando) | 7 | 🟠 Médio |
| Problemas de segurança | 5 | 🔴 Alto |
| Performance / N+1 queries | 4 | 🟡 Médio |
| Problemas de arquitetura | 3 | 🟡 Médio |
| Módulos gap total (ERP/Fiscal) | 6 sub-módulos | 🟢 Planejado |

---

## PARTE 1 — BUGS CRÍTICOS

### BUG-01 🔴 Margem de consignação sempre retorna zero

**Arquivo:** `routes/consignacao.php`  
**Causa:** O código usa `df.DETALHE_TIPO` e `df.DETALHE_VALOR` que **não existem** na tabela `DETALHE_FOLHA`. Os campos reais são `DETALHE_FOLHA_PROVENTOS`, `DETALHE_FOLHA_DESCONTOS`, `DETALHE_FOLHA_LIQUIDO`. Resultado: `liquido = 0` sempre → nenhum contrato é bloqueado por margem excedida.

**Onde corrigir (2 lugares):**

`GET /consignacao/margem/{funcionario_id}` — substituir o `DB::select` por:
```php
$folha = DB::table('DETALHE_FOLHA as df')
    ->join('FOLHA as fo', 'fo.FOLHA_ID', '=', 'df.FOLHA_ID')
    ->where('df.FUNCIONARIO_ID', $funcionario_id)
    ->orderBy('fo.FOLHA_COMPETENCIA', 'desc')
    ->select(
        DB::raw('COALESCE(df.DETALHE_FOLHA_PROVENTOS, 0) as creditos'),
        DB::raw('COALESCE(df.DETALHE_FOLHA_LIQUIDO, 0) as liquido')
    )
    ->first();

$liquido = $folha ? (float) $folha->liquido : 0;
$bruto   = $folha ? (float) $folha->creditos : 0;
$margem  = round($liquido * 0.35, 2);
```

`POST /consignacao` — substituir o bloco de cálculo de `$liquido` por:
```php
$liquido = (float) DB::table('DETALHE_FOLHA as df')
    ->join('FOLHA as fo', 'fo.FOLHA_ID', '=', 'df.FOLHA_ID')
    ->where('df.FUNCIONARIO_ID', $request->funcionario_id)
    ->orderBy('fo.FOLHA_COMPETENCIA', 'desc')
    ->value(DB::raw('COALESCE(df.DETALHE_FOLHA_LIQUIDO, 0)'));

$margemTotal = $liquido * 0.35;
```

---

### BUG-02 🔴 Guard de rota Vue com lógica invertida

**Arquivo:** `resources/gente-v3/src/router/index.js`  
**Função:** `hasAccess(perfil, requiredRoles)`

**Hierarquia atual:** `ROLE_HIERARCHY = ['admin', 'rh', 'gestor', 'funcionario']` — índice 0 = mais permissivo.

**Problema:** A comparação atual `indexOf(r) >= roleLevel` permite que índices maiores (menos privilegiados) acessem rotas de índice menor.

**Correção:**
```js
function hasAccess(perfil, requiredRoles) {
    if (!requiredRoles || requiredRoles.length === 0) return true
    const role = userRole(perfil)
    const userLevel = ROLE_HIERARCHY.indexOf(role)
    if (userLevel === -1) return false
    const minRequired = Math.min(
        ...requiredRoles
            .map(r => ROLE_HIERARCHY.indexOf(r))
            .filter(i => i !== -1)
    )
    return userLevel <= minRequired
}
```

---

### BUG-03 🔴 `fetchUser()` sem cache — request HTTP em cada navegação

**Arquivo:** `resources/gente-v3/src/store/auth.js`

**Correção — adicionar TTL de 5 minutos:**
```js
state: () => ({
    user: null,
    loading: false,
    _lastFetch: null,
}),

async fetchUser(force = false) {
    const TTL = 5 * 60 * 1000
    if (!force && this.user && this._lastFetch && (Date.now() - this._lastFetch) < TTL) return
    this.loading = true
    try {
        const { data } = await api.get('/api/auth/me')
        this.user = typeof data === 'string'
            ? JSON.parse(data.replace(/^\uFEFF/, '').trim())
            : data
        this._lastFetch = Date.now()
    } catch (error) {
        if (error?.response?.status === 401) {
            this.user = null
            this._lastFetch = null
        }
    } finally {
        this.loading = false
    }
}
```

---

### BUG-04 🔴 `fetch()` manual em vez do plugin axios (CSRF frágil)

**Arquivos afetados:** `ExoneracaoView.vue`, `HoraExtraView.vue` e qualquer outra view com `fetch()` nativo.

**Problema:** Extrai CSRF-token via regex de cookie (`document.cookie.match(/XSRF-TOKEN=.../)`). O token é URL-encoded e pode falhar silenciosamente, causando erro 419 sem mensagem clara.

**Regra:** Substituir **todos** os `fetch()` manuais pelo plugin `api` (axios já configurado):
```js
import api from '@/plugins/axios'

// ANTES:
const r = await fetch('/api/v3/rota', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': ... },
    body: JSON.stringify(payload)
})
const d = await r.json()

// DEPOIS:
const { data: d } = await api.post('/api/v3/rota', payload)
```

**Views para corrigir:**
- `ExoneracaoView.vue` — 5 ocorrências
- `HoraExtraView.vue` — 3 ocorrências
- Verificar: `VerbaIndenizatoriaView.vue`, `ConsignacaoView.vue`, `ProgressaoAdminView.vue`

---

### BUG-05 🟠 Pendências eSocial com subquery correlacionada O(n²)

**Arquivo:** `routes/esocial.php`, endpoint `GET /esocial/pendencias`

**Problema:** Usa `whereNull(DB::raw('(SELECT EVENTO_ID FROM ESOCIAL_EVENTO WHERE ... LIMIT 1)'))` em cada linha do resultado.

**Correção com LEFT JOIN:**
```php
// Admissões sem S-2200:
$admissoes = DB::table('FUNCIONARIO as f')
    ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
    ->leftJoin('ESOCIAL_EVENTO as e', function($j) {
        $j->on('e.FUNCIONARIO_ID', '=', 'f.FUNCIONARIO_ID')
          ->where('e.TIPO_EVENTO', 'S-2200');
    })
    ->whereNull('e.EVENTO_ID')
    ->select('f.FUNCIONARIO_ID', 'p.PESSOA_NOME', 'f.FUNCIONARIO_DATA_INICIO',
             DB::raw('"S-2200" as evento_faltante'))
    ->limit(50)->get();

// Desligamentos sem S-2299 (mesmo padrão com TIPO_EVENTO = 'S-2299')
```

---

### BUG-06 🟡 Typo em campo de histórico funcional

**Arquivo:** `routes/progressao_funcional.php`

**Linha:**
```php
// ERRADO (typo: HISTORARIO com A invertido):
'salario_de' => $h->HISTORARIO_SALARIO_ANTES ?? $h->HISTORICO_SALARIO_ANTES,

// CORRETO:
'salario_de' => $h->HISTORICO_SALARIO_ANTES,
```

---

### BUG-07 🟡 `diarias.php` registrado com prefix próprio duplicando o grupo

**Arquivo:** `routes/diarias.php`

**Problema:** O arquivo abre `Route::middleware(['auth'])->prefix('api/v3')->group(...)` próprio, mas é incluído com `require` dentro do grupo `api/v3` do `web.php`. Isso cria rotas duplicadas com path `/api/v3/api/v3/diarias` (falha silenciosa — a rota registrada sem o grupo externo acaba funcionando mas sem o middleware `web` do grupo pai).

**Correção em `routes/diarias.php`:** remover o `Route::middleware(...)->prefix('api/v3')->group(...)` wrapper e deixar apenas os `Route::get/post/patch` diretamente, já que o contexto do `require` herda o grupo do `web.php`.

**Mesmo padrão em `routes/rpps.php`** — verificar e corrigir.

---

## PARTE 2 — SEGURANÇA

### SEC-01 🔴 Rotas `/dev/*` expostas em produção via condition frágil

**Arquivo:** `routes/web.php`

**Problema:** As rotas `/dev/ping-db`, `/dev/echo-request`, `/dev/echo-raw`, `/dev/diag-login/{login}/{senha}`, `/dev/set-senha/{login}/{senha}`, `/dev/criar-admin`, `/dev/seed-dados` usam `if (app()->environment('production')) abort(404)`. Um erro de configuração de `APP_ENV` expõe reset de senha e criação de admin sem autenticação.

**Correção:** Mover TODOS para um grupo com verificação dupla:
```php
if (app()->isLocal() || app()->environment('development', 'testing')) {
    Route::prefix('dev')->group(function () {
        Route::get('/ping-db', ...);
        Route::get('/set-senha/{login}/{senha}', ...); // ESPECIALMENTE ESTE
        Route::get('/criar-admin', ...);
        // ... demais
    });
}
```
Ou melhor: **deletar todos** antes do deploy e manter apenas em branch local.

---

### SEC-02 🔴 `/dev/set-senha/{login}/{senha}` — vetor de ataque crítico

**Arquivo:** `routes/web.php`  
**Rota:** `GET /dev/set-senha/{login}/{senha}`

Qualquer pessoa que conheça a URL consegue redefinir a senha de qualquer usuário e ativar a conta sem autenticação. Esta rota **não deve existir em ambiente acessível**.

**Ação:** Deletar imediatamente ou envolver em `app()->isLocal()`.

---

### SEC-03 🟠 Admin hardcoded sem log de auditoria

**Arquivo:** `routes/web.php` (endpoints `/api/auth/me` e `/api/auth/login`)

O usuário `admin` bypassa a tabela `USUARIO_PERFIL`. Isso é correto para dev, mas em produção precisa de rastreabilidade.

**Adição imediata:**
```php
if (strtolower($user->USUARIO_LOGIN) === 'admin') {
    $perfilNome = 'admin';
    \Log::channel('security')->info('Login admin hardcoded', [
        'ip' => request()->ip(),
        'at' => now()->toIso8601String(),
        'user_agent' => request()->userAgent(),
    ]);
}
```

---

### SEC-04 🟠 JWT do app mobile com `config('app.key')` como segredo HMAC

**Arquivo:** `routes/ponto_app.php`

O token JWT do app de ponto usa `config('app.key')` como segredo. O `APP_KEY` é a mesma chave usada para criptografia de sessões Laravel. Se vazar, compromete todo o sistema (sessões + tokens).

**Correção:** Usar uma chave separada:
```php
// .env
PONTO_APP_JWT_SECRET=chave_separada_32_chars_aleatoria

// ponto_app.php:
$secret = config('services.ponto_app.jwt_secret', config('app.key'));
```
```php
// config/services.php:
'ponto_app' => [
    'jwt_secret' => env('PONTO_APP_JWT_SECRET'),
],
```

---

### SEC-05 🟠 Senhas MD5 não migradas para usuários sem login recente

A migração MD5→bcrypt só ocorre no login. Usuários inativos mantêm MD5 indefinidamente.

**Seeder de migração forçada:**
```php
// database/seeders/MigrarSenhasMd5Seeder.php
$migrados = 0;
\App\Models\Usuario::all()->each(function($u) use (&$migrados) {
    if (preg_match('/^[a-f0-9]{32}$/', $u->USUARIO_SENHA)) {
        $u->USUARIO_ALTERAR_SENHA = 1; // Força troca na próxima entrada
        $u->save();
        $migrados++;
    }
});
// Rodar: php artisan db:seed --class=MigrarSenhasMd5Seeder
```

---

## PARTE 3 — GAPS DE BACKEND (endpoints faltando para views Vue existentes)

### GAP-01 🟠 Endpoint `/notificacoes` faltando (polling ativo no layout)

**Afeta:** `DashboardLayout.vue` — faz polling a cada 60s em `GET /api/v3/notificacoes`. Como não existe, gera 404 constante nos logs.

**Criar no `web.php` dentro do grupo `api/v3`:**
```php
Route::get('/notificacoes', function (Request $request) {
    $user = Auth::user();
    // Stub funcional — implementar notificações reais depois
    return response()->json(['notificacoes' => [], 'nao_lidas' => 0]);
});

Route::post('/notificacoes/{id}/ler', function ($id) {
    return response()->json(['ok' => true]);
});

Route::post('/notificacoes/ler-todas', function () {
    return response()->json(['ok' => true]);
});
```

---

### GAP-02 🟠 Banco de Horas sem backend

**Rota Vue:** `/banco-horas` → `ponto/BancoHorasView.vue`

**Migration necessária:**
```php
// 2026_03_12_create_banco_horas_table.php
Schema::create('BANCO_HORAS', function (Blueprint $table) {
    $table->increments('BANCO_HORAS_ID');
    $table->unsignedInteger('FUNCIONARIO_ID');
    $table->string('COMPETENCIA', 7);
    $table->decimal('HORAS_CREDITADAS', 6, 2)->default(0);
    $table->decimal('HORAS_DEBITADAS', 6, 2)->default(0);
    $table->decimal('SALDO', 6, 2)->default(0);
    $table->string('TIPO', 20); // CREDITO | COMPENSACAO | PAGAMENTO | EXPIRADO
    $table->text('OBSERVACAO')->nullable();
    $table->unsignedInteger('REGISTRADO_POR')->nullable();
    $table->timestamps();
});
```

**Endpoints (criar arquivo `routes/banco_horas.php`):**
```php
Route::get('/banco-horas', fn(Request $r) => response()->json([
    'saldo' => DB::table('BANCO_HORAS')
        ->where('FUNCIONARIO_ID', $r->funcionario_id ?? Auth::user()->FUNCIONARIO_ID)
        ->orderByDesc('COMPETENCIA')->first(),
    'historico' => DB::table('BANCO_HORAS')
        ->where('FUNCIONARIO_ID', $r->funcionario_id ?? Auth::user()->FUNCIONARIO_ID)
        ->orderByDesc('COMPETENCIA')->limit(12)->get()
]));

Route::post('/banco-horas/compensar', function (Request $r) { /* lançar débito */ });
Route::get('/banco-horas/relatorio', function (Request $r) { /* consolidado por secretaria */ });
```

---

### GAP-03 🟠 Atestados Médicos sem backend

**Rota Vue:** `/atestados-medicos` → `ponto/AtestadosMedicosView.vue`

**Migration:**
```php
Schema::create('ATESTADO_MEDICO', function (Blueprint $table) {
    $table->increments('ATESTADO_ID');
    $table->unsignedInteger('FUNCIONARIO_ID');
    $table->date('ATESTADO_DATA');
    $table->integer('ATESTADO_DIAS');
    $table->string('ATESTADO_CID', 10)->nullable();
    $table->string('MEDICO_NOME', 150)->nullable();
    $table->string('MEDICO_CRM', 20)->nullable();
    $table->string('ARQUIVO_PATH', 300)->nullable();
    $table->string('STATUS', 20)->default('PENDENTE'); // PENDENTE | VALIDADO | REJEITADO
    $table->unsignedInteger('VALIDADO_POR')->nullable();
    $table->timestamps();
});
```

**Endpoints:**
```php
Route::get('/atestados', ...);   // lista com filtro perfil
Route::post('/atestados', ...);  // registrar
Route::patch('/atestados/{id}/validar', ...); // RH valida
```

---

### GAP-04 🟠 Endpoint genérico `/servidores/buscar` faltando

**Problema:** `ExoneracaoView.vue` e `HoraExtraView.vue` reutilizam `/api/v3/exoneracao/buscar` para autocomplete de servidor em módulos diferentes. Isso cria acoplamento incorreto.

**Criar endpoint dedicado e reutilizável:**
```php
Route::get('/servidores/buscar', function (Request $request) {
    $q = $request->q ?? '';
    $servidores = DB::table('FUNCIONARIO as f')
        ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
        ->leftJoin('CARGO as c', 'c.CARGO_ID', '=', 'f.CARGO_ID')
        ->leftJoin('LOTACAO as l', function($j) {
            $j->on('l.FUNCIONARIO_ID', '=', 'f.FUNCIONARIO_ID')
              ->whereNull('l.LOTACAO_DATA_FIM');
        })
        ->leftJoin('SETOR as s', 's.SETOR_ID', '=', 'l.SETOR_ID')
        ->leftJoin('UNIDADE as u', 'u.UNIDADE_ID', '=', 's.UNIDADE_ID')
        ->whereNull('f.FUNCIONARIO_DATA_FIM')
        ->where(fn($w) => $w
            ->where('p.PESSOA_NOME', 'like', "%$q%")
            ->orWhere('f.FUNCIONARIO_MATRICULA', 'like', "%$q%"))
        ->select(
            'f.FUNCIONARIO_ID as id',
            'p.PESSOA_NOME as nome',
            'f.FUNCIONARIO_MATRICULA as matricula',
            'f.FUNCIONARIO_DATA_INICIO as admissao',
            'f.FUNCIONARIO_REGIME_PREV as regime_prev',
            'c.CARGO_NOME as cargo',
            'c.CARGO_SALARIO',
            'f.CARGO_ID', 'f.CARREIRA_ID',
            'f.FUNCIONARIO_CLASSE', 'f.FUNCIONARIO_REFERENCIA',
            's.SETOR_NOME as setor',
            'u.UNIDADE_NOME as secretaria',
            'u.UNIDADE_ID as unidade_id'
        )
        ->limit(15)->get();
    return response()->json(['servidores' => $servidores]);
});
```

**Depois atualizar todas as views** para usar `/api/v3/servidores/buscar`.

---

### GAP-05 🟠 Folha: endpoints `/folhas` e `/folhas/calcular` não encontrados no web.php

**Afeta:** `FolhaPagamentoView.vue` usa `GET /api/v3/folhas`, `POST /api/v3/folhas/calcular`, `GET /api/v3/folhas/{id}/detalhes`. A view tem fallback com dados mock, mas em produção retornará sempre mock.

**Criar endpoints:**
```php
// GET /folhas — lista folhas com totais consolidados
Route::get('/folhas', function (Request $request) {
    $folhas = DB::table('FOLHA as f')
        ->leftJoin('DETALHE_FOLHA as df', 'df.FOLHA_ID', '=', 'f.FOLHA_ID')
        ->groupBy('f.FOLHA_ID', 'f.FOLHA_COMPETENCIA', 'f.FOLHA_TIPO_ESPECIAL', 'f.FOLHA_SITUACAO')
        ->select(
            'f.FOLHA_ID', 'f.FOLHA_COMPETENCIA', 'f.FOLHA_TIPO_ESPECIAL', 'f.FOLHA_SITUACAO',
            DB::raw('COUNT(DISTINCT df.FUNCIONARIO_ID) as qtd_funcionarios'),
            DB::raw('SUM(COALESCE(df.DETALHE_FOLHA_PROVENTOS,0)) as total_proventos'),
            DB::raw('SUM(COALESCE(df.DETALHE_FOLHA_DESCONTOS,0)) as total_descontos'),
            DB::raw('SUM(COALESCE(df.DETALHE_FOLHA_LIQUIDO,0)) as total_liquido')
        )
        ->orderByDesc('f.FOLHA_COMPETENCIA')
        ->limit(24)->get();
    return response()->json(['folhas' => $folhas]);
});

// POST /folhas/calcular — consolida os totais de uma competência já existente
Route::post('/folhas/calcular', function (Request $request) {
    $comp = str_replace('-', '', $request->competencia); // AAAAMM
    $folha = DB::table('FOLHA')->where('FOLHA_COMPETENCIA', $comp)->first();
    if (!$folha) return response()->json(['erro' => 'Folha não encontrada para esta competência.'], 404);

    // Atualiza DETALHE_FOLHA_LIQUIDO = PROVENTOS - DESCONTOS
    DB::table('DETALHE_FOLHA')->where('FOLHA_ID', $folha->FOLHA_ID)
        ->update([DB::raw('DETALHE_FOLHA_LIQUIDO = DETALHE_FOLHA_PROVENTOS - DETALHE_FOLHA_DESCONTOS')]);

    $totais = DB::table('DETALHE_FOLHA')->where('FOLHA_ID', $folha->FOLHA_ID)
        ->selectRaw('COUNT(DISTINCT FUNCIONARIO_ID) as qtd, SUM(DETALHE_FOLHA_LIQUIDO) as liquido')
        ->first();

    return response()->json([
        'ok' => true,
        'mensagem' => "Folha {$request->competencia} calculada.",
        'qtd_funcionarios' => $totais->qtd ?? 0,
        'total_liquido' => $totais->liquido ?? 0,
    ]);
});

// GET /folhas/{id}/detalhes
Route::get('/folhas/{id}/detalhes', function ($id) {
    $detalhes = DB::table('DETALHE_FOLHA as df')
        ->join('PESSOA as p', 'p.PESSOA_ID', '=', DB::raw('(SELECT PESSOA_ID FROM FUNCIONARIO WHERE FUNCIONARIO_ID = df.FUNCIONARIO_ID)'))
        ->where('df.FOLHA_ID', $id)
        ->select('df.FUNCIONARIO_ID', 'p.PESSOA_NOME as nome',
                 'df.DETALHE_FOLHA_PROVENTOS as proventos',
                 'df.DETALHE_FOLHA_DESCONTOS as descontos',
                 'df.DETALHE_FOLHA_LIQUIDO as liquido')
        ->get();
    return response()->json(['detalhes' => $detalhes]);
});
```

---

### GAP-06 🟡 Holerites: endpoint `GET /meus-holerites` existe mas `GET /meus-holerites/{id}/pdf` não

**Afeta:** `ContraChequeView.vue` — botão "Baixar PDF" faz `window.open('/api/v3/meus-holerites/{id}/pdf')`. Retorna 404.

**Criar endpoint PDF básico:**
```php
Route::get('/meus-holerites/{id}/pdf', function ($id) {
    // Por ora: redireciona para o detalhe JSON ou gera PDF simples com DomPDF
    $detalhe = DB::table('DETALHE_FOLHA as df')
        ->join('FOLHA as f', 'f.FOLHA_ID', '=', 'df.FOLHA_ID')
        ->join('PESSOA as p', 'p.PESSOA_ID', '=',
               DB::raw('(SELECT PESSOA_ID FROM FUNCIONARIO WHERE FUNCIONARIO_ID = df.FUNCIONARIO_ID)'))
        ->where('df.DETALHE_FOLHA_ID', $id)
        ->first();

    if (!$detalhe) abort(404);

    // TODO: usar barryvdh/laravel-dompdf para gerar PDF real
    // Por enquanto retorna JSON para não quebrar
    return response()->json(['detalhe' => $detalhe, 'aviso' => 'PDF em implementação']);
});
```

---

### GAP-07 🟡 Secretarias: `GET /secretarias` chamado mas não registrado

**Afeta:** `FolhaPagamentoView.vue` faz `api.get('/api/v3/secretarias')` no `onMounted`.

**Criar:**
```php
Route::get('/secretarias', function () {
    return response()->json([
        'unidades' => DB::table('UNIDADE')
            ->where('UNIDADE_ATIVO', 1)
            ->orderBy('UNIDADE_NOME')
            ->get(['UNIDADE_ID', 'UNIDADE_NOME'])
    ]);
});
```

---

## PARTE 4 — PERFORMANCE

### PERF-01 🟡 Progressão funcional: N+1 na listagem admin

**Arquivo:** `routes/progressao_funcional.php`, endpoints `admin` e `lista-elegiveis`

**Problema:** Para cada funcionário faz queries separadas em `AVALIACAO_DESEMPENHO` e `AFASTAMENTO`.

**Correção:**
```php
// Buscar ANTES do loop, em lote:
$funcIds = $lista->pluck('FUNCIONARIO_ID');

$avaliacoes = DB::table('AVALIACAO_DESEMPENHO')
    ->whereIn('FUNCIONARIO_ID', $funcIds)
    ->orderByDesc('created_at')
    ->get()->groupBy('FUNCIONARIO_ID')
    ->map(fn($g) => $g->first());

$comPenalidade = DB::table('AFASTAMENTO')
    ->whereIn('FUNCIONARIO_ID', $funcIds)
    ->where(fn($q) => $q
        ->whereRaw("LOWER(AFASTAMENTO_TIPO) LIKE '%disciplinar%'")
        ->orWhereRaw("LOWER(AFASTAMENTO_TIPO) LIKE '%suspen%'"))
    ->where(fn($q) => $q->whereNull('AFASTAMENTO_DATA_FIM')
        ->orWhere('AFASTAMENTO_DATA_FIM', '>=', now()))
    ->pluck('FUNCIONARIO_ID')->flip()->toArray();

// Passar $avaliacoes[$func->FUNCIONARIO_ID] e isset($comPenalidade[$funcId])
// para dentro do closure do ->map(), evitando queries por linha
```

---

### PERF-02 🟡 Índices de banco ausentes

**Migration: `2026_03_12_add_performance_indexes.php`**
```php
// DETALHE_FOLHA — queries mais frequentes do sistema
Schema::table('DETALHE_FOLHA', function (Blueprint $table) {
    $table->index(['FUNCIONARIO_ID', 'FOLHA_ID'], 'idx_df_func_folha');
    $table->index(['UNIDADE_ID', 'FOLHA_ID'], 'idx_df_unidade_folha');
});

Schema::table('FOLHA', function (Blueprint $table) {
    $table->index('FOLHA_COMPETENCIA', 'idx_folha_comp');
    $table->index(['FOLHA_COMPETENCIA', 'FOLHA_TIPO_ESPECIAL'], 'idx_folha_comp_tipo');
});

Schema::table('HORA_EXTRA', function (Blueprint $table) {
    $table->index(['COMPETENCIA', 'STATUS'], 'idx_he_comp_status');
});

Schema::table('CONSIG_CONTRATO', function (Blueprint $table) {
    $table->index(['FUNCIONARIO_ID', 'STATUS'], 'idx_cc_func_status');
});

Schema::table('ESOCIAL_EVENTO', function (Blueprint $table) {
    $table->index(['FUNCIONARIO_ID', 'TIPO_EVENTO'], 'idx_es_func_tipo');
    $table->index('STATUS', 'idx_es_status');
});

Schema::table('LOTACAO', function (Blueprint $table) {
    $table->index(['FUNCIONARIO_ID', 'LOTACAO_DATA_FIM'], 'idx_lot_func_fim');
});
```

---

### PERF-03 🟡 Polling de notificações sem debounce — sempre ativo mesmo com aba em background

**Arquivo:** `DashboardLayout.vue`

**Adicionar visibilidade do documento para pausar polling:**
```js
// No onMounted:
notifInterval = setInterval(() => {
    if (!document.hidden) fetchNotif() // não busca quando aba está em background
}, 60_000)

// No onUnmounted:
if (notifInterval) clearInterval(notifInterval)
```

---

### PERF-04 🟡 RPPS: alíquotas patronal/servidor hardcoded (28%/14%)

**Arquivo:** `routes/rpps.php`

As alíquotas `0.14` e `0.28` estão fixas no código. Para São Luís (IPAM), as alíquotas podem diferir.

**Extrair para configuração:**
```php
// Criar tabela RPPS_CONFIG ou ler de PARAMETRO_SISTEMA:
$cfg = DB::table('RPPS_CONFIG')->orderByDesc('VIGENCIA_INICIO')->first();
$aliqServidor  = ($cfg->ALIQUOTA_SERVIDOR ?? 14) / 100;
$aliqPatronal  = ($cfg->ALIQUOTA_PATRONAL ?? 22) / 100; // IPAM: 22% patronal
```

---

## PARTE 5 — PROBLEMAS DE ARQUITETURA

### ARQ-01 🟡 `web.php` com 512 KB — monolito de rotas ingerenciável

**Problema:** O arquivo tem 512 KB e mistura auth, CRUD de funcionários, folha, ponto, config, seed e diagnóstico. Com o crescimento para ERP/Fiscal ficará impossível de manter.

**Refatoração gradual — criar arquivos modulares:**
```
routes/
  web.php              ← mantém: SPA, auth, rotas raiz, dev-only, require dos módulos
  funcionarios.php     ← GET/PUT /funcionarios, /documentos, /historico, /escalas
  folha.php            ← /folhas, /meus-holerites, /calcular
  ponto_web.php        ← /ponto (GET batidas do mês, sessão web)
  configuracoes.php    ← /config/*, /parametros/*, /tabelas-auxiliares
  autocadastro.php     ← /autocadastro/*, /validar-token
  sagres.php           ← integração TCE-MA (futuro)
```

Adicionar no final do grupo api/v3 do `web.php`:
```php
require __DIR__ . '/funcionarios.php';
require __DIR__ . '/folha.php';
require __DIR__ . '/banco_horas.php';
// ... etc
```

---

### ARQ-02 🟡 Módulos `diarias.php` e `rpps.php` com grupo próprio duplicado

**Já descrito em BUG-07.** O `require` dentro do grupo `api/v3` + grupo próprio dentro do arquivo cria rotas em path errado.

**Regra para todos os arquivos de rotas incluídos por `require`:** nunca reabrir um grupo, nunca usar `Route::prefix()` ou `Route::middleware()` próprios. Herdar o contexto do grupo pai.

---

### ARQ-03 🟡 Rota `/v3` exposta publicamente sem autenticação

**Arquivo:** `routes/web.php`

```php
Route::get('/v3', function () {
    return view('v3.app');
});
```

Esta rota serve o SPA sem verificar autenticação. O Vue Router protege as páginas individuais, mas o HTML/JS do app é entregue para qualquer visitante. Considerar adicionar middleware `auth` se o app não for público:

```php
Route::get('/v3/{any?}', function () {
    return view('v3.app');
})->where('any', '.*')->middleware('auth');
```

Ou manter público se a tela de login é a primeira view (padrão SPA).

---

## PARTE 6 — MÓDULO ERP/FISCAL (gap total — nenhum arquivo existe)

### Ordem de implementação recomendada:

**Sprint ERP-1: Estrutura base**
```
migrations/2026_03_15_create_orcamento_tables.php
  → ORCAMENTO_PPA, ORCAMENTO_PROGRAMA, ORCAMENTO_ACAO, ORCAMENTO_LOA

routes/orcamento.php
  → GET /orcamento/ppa, GET /orcamento/loa?ano=, GET /orcamento/resumo
  → POST /orcamento/ppa, POST /orcamento/acao, POST /orcamento/loa

views/financeiro/OrcamentoView.vue
  → Abas: PPA | LOA por Ação | Gráfico Execução
```

**Sprint ERP-2: Execução da Despesa**
```
migrations/2026_03_15_create_execucao_despesa_tables.php
  → EMPENHO, LIQUIDACAO, PAGAMENTO_DESPESA

routes/execucao_despesa.php
  → GET/POST /empenho, POST /empenho/{id}/liquidar, POST /liquidacao/{id}/pagar
  → GET /empenho/resumo-acao

views/financeiro/ExecucaoDespesaView.vue
  → Abas: Empenhos | Liquidações | Pagamentos | Saldo por Ação
```

**Sprint ERP-3: Contabilidade**
```
migrations/2026_03_16_create_contabilidade_tables.php
  → PCASP_CONTA (hierarquia), LANCAMENTO_CONTABIL

routes/contabilidade.php
  → GET /pcasp, POST /lancamentos, GET /balancete?mes=&ano=

views/financeiro/ContabilidadeView.vue
  → Plano de Contas | Lançamentos | Balancete
```

**Sprint ERP-4: Tesouraria**
```
migrations/2026_03_16_create_tesouraria_tables.php
  → CONTA_BANCARIA, MOVIMENTACAO_BANCARIA

routes/tesouraria.php
  → GET /contas-bancarias, GET /fluxo-caixa, POST /conciliar

views/financeiro/TesourariaView.vue
  → Contas | Extrato | Conciliação | Fluxo de Caixa
```

**Sprint ERP-5: Receita Municipal**
```
migrations/2026_03_17_create_receita_tables.php
  → RECEITA_LANCAMENTO, RECEITA_DIVIDA_ATIVA

routes/receita_municipal.php
  → GET /receita, POST /receita, GET /receita/por-tipo

views/financeiro/ReceitaMunicipalView.vue
```

**Sprint ERP-6: Controle Externo (TCE-MA/SICONFI)**
```
migrations/2026_03_17_create_controle_externo_tables.php
  → SAGRES_DEPARA, EXPORTACAO_CONTROLE

routes/controle_externo.php
  → GET /sagres/preview, POST /sagres/gerar, GET /siconfi/rreo, GET /siconfi/rgf

views/financeiro/ControleExternoView.vue
  → SAGRES SINC-Folha | SICONFI | RGF | RREO
```

**Para cada sprint ERP, registrar no `web.php`:**
```php
require __DIR__ . '/orcamento.php';
require __DIR__ . '/execucao_despesa.php';
// ... etc
```

**E no `router/index.js`:**
```js
{ path: 'orcamento',         component: () => import('../views/financeiro/OrcamentoView.vue'),        meta: { roles: ['admin'] } },
{ path: 'execucao-despesa',  component: () => import('../views/financeiro/ExecucaoDespesaView.vue'),  meta: { roles: ['admin'] } },
{ path: 'contabilidade',     component: () => import('../views/financeiro/ContabilidadeView.vue'),    meta: { roles: ['admin'] } },
{ path: 'tesouraria',        component: () => import('../views/financeiro/TesourariaView.vue'),       meta: { roles: ['admin'] } },
{ path: 'receita-municipal', component: () => import('../views/financeiro/ReceitaMunicipalView.vue'), meta: { roles: ['admin'] } },
{ path: 'controle-externo',  component: () => import('../views/financeiro/ControleExternoView.vue'),  meta: { roles: ['admin'] } },
```

---

## CHECKLIST CONSOLIDADO (ordem de prioridade)

### Bloco 1 — Urgente (bugs que quebram funcionalidade real)
- [ ] BUG-01 — Corrigir cálculo de margem consignação (2 lugares em `consignacao.php`)
- [ ] BUG-02 — Corrigir `hasAccess()` no `router/index.js`
- [ ] BUG-03 — Cache TTL no `fetchUser()` (`store/auth.js`)
- [ ] BUG-04 — Substituir todos os `fetch()` manuais por `api` axios
- [ ] SEC-02 — Remover/bloquear rota `/dev/set-senha` **imediatamente**
- [ ] GAP-01 — Criar stub de endpoint `/notificacoes` (para parar 404 no polling)

### Bloco 2 — Alta prioridade (segurança e correções menores)
- [ ] BUG-05 — Corrigir subquery eSocial para LEFT JOIN
- [ ] BUG-06 — Corrigir typo `HISTORARIO_SALARIO_ANTES`
- [ ] BUG-07 — Corrigir grupo duplicado em `diarias.php` e `rpps.php`
- [ ] SEC-01 — Envolver todas as rotas `/dev/*` em `app()->isLocal()`
- [ ] SEC-03 — Adicionar log de auditoria no bypass admin
- [ ] SEC-04 — Chave JWT separada para app de ponto
- [ ] SEC-05 — Seeder de migração forçada MD5 → bcrypt

### Bloco 3 — Médio prazo (gaps de funcionalidade)
- [ ] GAP-02 — Banco de Horas: migration + endpoints
- [ ] GAP-03 — Atestados Médicos: migration + endpoints
- [ ] GAP-04 — Endpoint genérico `/servidores/buscar` + refatorar views
- [ ] GAP-05 — Endpoints `/folhas`, `/folhas/calcular`, `/folhas/{id}/detalhes`
- [ ] GAP-06 — Endpoint `/meus-holerites/{id}/pdf` (stub ou DomPDF)
- [ ] GAP-07 — Endpoint `/secretarias`
- [ ] PERF-01 — Corrigir N+1 na listagem admin de progressão funcional
- [ ] PERF-02 — Migration de índices de performance
- [ ] PERF-03 — Pausar polling de notificações quando aba está em background
- [ ] PERF-04 — Externalizar alíquotas RPPS para tabela configurável

### Bloco 4 — Refatoração / Arquitetura
- [ ] ARQ-01 — Extrair rotas do `web.php` em arquivos modulares
- [ ] ARQ-02 — Padronizar todos os arquivos de rotas `require`'d sem grupo próprio

### Bloco 5 — Módulo ERP/Fiscal (6 sprints)
- [ ] Sprint ERP-1: Orçamento (PPA/LOA)
- [ ] Sprint ERP-2: Execução da Despesa
- [ ] Sprint ERP-3: Contabilidade Pública (PCASP)
- [ ] Sprint ERP-4: Tesouraria
- [ ] Sprint ERP-5: Receita Municipal
- [ ] Sprint ERP-6: Controle Externo (TCE-MA/SICONFI/RGF/RREO)

---

## MAPA DE ARQUIVOS — STATUS COMPLETO

| Módulo | Backend | Frontend | Migration | Status |
|--------|---------|----------|-----------|--------|
| Auth / Sessão | `web.php` | `LoginView.vue` | — | ✅ OK |
| Funcionários | `web.php` | `FuncionariosView.vue` | legacy | ✅ OK |
| Perfil Funcionário | `web.php` | `PerfilFuncionarioView.vue` | legacy | ✅ OK |
| Holerites | `web.php` (parcial) | `ContraChequeView.vue` | — | ⚠️ PDF faltando |
| Folha Pagamento | ❌ faltando | `FolhaPagamentoView.vue` | `000002` | ❌ GAP-05 |
| Exoneração | `exoneracao.php` | `ExoneracaoView.vue` | `000001,000005` | ✅ OK |
| Hora Extra | `hora_extra.php` | `HoraExtraView.vue` | `000006` | ✅ OK |
| Plantão Extra | `hora_extra.php` | `PlantoesExtrasView.vue` | `000006` | ✅ OK |
| Verba Indenizatória | `verba_indenizatoria.php` | `VerbaIndenizatoriaView.vue` | `000004` | ✅ OK |
| Consignação | `consignacao.php` | `ConsignacaoView.vue` | `000007` | ⚠️ BUG-01 |
| eSocial | `esocial.php` | `ESocialView.vue` | `000008` | ⚠️ BUG-05 |
| Progressão Funcional | `progressao_funcional.php` | `ProgressaoFuncionalView.vue` | `progressao_tables` | ⚠️ BUG-06, PERF-01 |
| Diárias | `diarias.php` | (view não identificada) | — | ⚠️ BUG-07 |
| RPPS/IPAM | `rpps.php` | (view não identificada) | — | ⚠️ BUG-07, PERF-04 |
| App de Ponto (mobile) | `ponto_app.php` | — | — | ⚠️ SEC-04 |
| Banco de Horas | ❌ faltando | `BancoHorasView.vue` | ❌ faltando | ❌ GAP-02 |
| Atestados Médicos | ❌ faltando | `AtestadosMedicosView.vue` | ❌ faltando | ❌ GAP-03 |
| Notificações | ❌ faltando | `DashboardLayout.vue` | — | ❌ GAP-01 |
| Secretarias (lookup) | ❌ faltando | múltiplas views | — | ❌ GAP-07 |
| Orçamento Público | ❌ faltando | ❌ faltando | ❌ faltando | ❌ ERP sprint 1 |
| Execução Despesa | ❌ faltando | ❌ faltando | ❌ faltando | ❌ ERP sprint 2 |
| Contabilidade PCASP | ❌ faltando | ❌ faltando | ❌ faltando | ❌ ERP sprint 3 |
| Tesouraria | ❌ faltando | ❌ faltando | ❌ faltando | ❌ ERP sprint 4 |
| Receita Municipal | ❌ faltando | ❌ faltando | ❌ faltando | ❌ ERP sprint 5 |
| Controle Externo | ❌ faltando | ❌ faltando | ❌ faltando | ❌ ERP sprint 6 |

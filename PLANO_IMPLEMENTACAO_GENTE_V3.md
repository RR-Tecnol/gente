# GENTE v3 — Plano de Implementação Completo
**Gerado:** 10/03/2026 | **Revisão:** 11/03/2026 — pós-Sprint Antigravity + e-mail Brevo
**Contexto:** Sistema de Gestão de Pessoas — Prefeitura Municipal de São Luís / MA
**Stack:** Laravel (routes em web.php + arquivos modulares) + Vue 3 SPA (Vite)

---

## SUMÁRIO DE CRITICIDADE

| Categoria | Qtd | Impacto |
|-----------|-----|---------|
| Bugs críticos (quebram funcionalidade) | 7 | 🔴 Alto |
| Consignação — refatoração estrutural | 5 | 🔴 Alto |
| Segurança | 8 | 🔴/🟠 Alto |
| Gaps de backend (endpoints faltando) | 13 | 🟠 Médio |
| Performance / N+1 queries | 3 | 🟡 Médio |
| Arquitetura | 3 | 🟡 Médio |
| Módulos latentes (banco pronto, sem código) | 6 | 🟠/🔴 |
| **Desconexões Motor de Folha** | **6** | **🔴 Alto** |
| Módulo ERP/Fiscal — gap total | 6 sub-módulos | 🟢 Planejado |

---

## ÍNDICE

1. [Bugs Críticos](#1-bugs-críticos)
2. [Segurança](#2-segurança)
3. [Consignação — Refatoração Completa](#3-consignação)
4. [Gaps de Backend](#4-gaps-de-backend)
5. [Performance](#5-performance)
6. [Módulos Latentes](#6-módulos-latentes)
7. [Arquitetura](#7-arquitetura)
8. [ERP/Fiscal — Gap Total](#8-erp-fiscal)
9. [E-mail — Brevo](#9-email)
10. [Checklist por Sprint](#10-checklist)
11. [Mapa de Status Completo](#11-mapa-de-status)
12. [Desconexões Motor de Folha](#12-desconexoes-motor-folha)

---

## 1. BUGS CRÍTICOS

### BUG-01 🔴 Margem de consignação sempre retorna zero

**Arquivo:** `routes/consignacao.php`
**Causa:** O código usa `df.DETALHE_TIPO` e `df.DETALHE_VALOR` que **não existem** na tabela `DETALHE_FOLHA`. Os campos reais são `DETALHE_FOLHA_PROVENTOS`, `DETALHE_FOLHA_DESCONTOS`, `DETALHE_FOLHA_LIQUIDO`. Resultado: `liquido = 0` sempre → nenhum contrato é bloqueado por margem excedida.

**Corrigir em 2 lugares:**

`GET /consignacao/margem/{funcionario_id}`:
```php
$folha = DB::table('DETALHE_FOLHA as df')
    ->join('FOLHA as fo', 'fo.FOLHA_ID', '=', 'df.FOLHA_ID')
    ->where('df.FUNCIONARIO_ID', $funcionario_id)
    ->orderBy('fo.FOLHA_COMPETENCIA', 'desc')
    ->select(
        DB::raw('COALESCE(df.DETALHE_FOLHA_PROVENTOS, 0) as creditos'),
        DB::raw('COALESCE(df.DETALHE_FOLHA_LIQUIDO,   0) as liquido')
    )
    ->first();

$liquido = $folha ? (float) $folha->liquido  : 0;
$bruto   = $folha ? (float) $folha->creditos : 0;
```

`POST /consignacao`:
```php
$liquido = (float) DB::table('DETALHE_FOLHA as df')
    ->join('FOLHA as fo', 'fo.FOLHA_ID', '=', 'df.FOLHA_ID')
    ->where('df.FUNCIONARIO_ID', $request->funcionario_id)
    ->orderBy('fo.FOLHA_COMPETENCIA', 'desc')
    ->value(DB::raw('COALESCE(df.DETALHE_FOLHA_LIQUIDO, 0)'));
```

---

### BUG-02 🔴 Guard de rota Vue com lógica invertida

**Arquivo:** `resources/gente-v3/src/router/index.js`

**Hierarquia:** `ROLE_HIERARCHY = ['admin', 'rh', 'gestor', 'funcionario']` — índice 0 = mais permissivo. A comparação atual permite que índices maiores (menos privilegiados) acessem rotas de índice menor.

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

### BUG-04 🔴 `fetch()` manual com CSRF frágil em múltiplas views

**Views afetadas:** `ExoneracaoView.vue` (5 ocorrências), `HoraExtraView.vue` (3), `ConsignacaoView.vue`, `VerbaIndenizatoriaView.vue`, `ProgressaoAdminView.vue` — verificar.

**Problema:** Extrai CSRF via regex de cookie. O token é URL-encoded e pode falhar silenciosamente com erro 419 sem mensagem clara.

```js
// ANTES (problemático):
const r = await fetch('/api/v3/rota', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() },
    body: JSON.stringify(payload)
})
const d = await r.json()

// DEPOIS (correto):
import api from '@/plugins/axios'
const { data: d } = await api.post('/api/v3/rota', payload)
```

---

### BUG-05 🟠 Pendências eSocial com subquery correlacionada O(n²)

**Arquivo:** `routes/esocial.php`, endpoint `GET /esocial/pendencias`

```php
// Substituir subquery por LEFT JOIN:
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
// Mesmo padrão para S-2299 (desligamentos)
```

---

### BUG-06 🟡 Typo em campo de histórico funcional

**Arquivo:** `routes/progressao_funcional.php`
```php
// ERRADO:
'salario_de' => $h->HISTORARIO_SALARIO_ANTES,
// CORRETO:
'salario_de' => $h->HISTORICO_SALARIO_ANTES,
```

---

### BUG-07 🟡 `diarias.php` e `rpps.php` com grupo de rotas duplicado

**Problema:** Ambos abrem `Route::middleware(['auth'])->prefix('api/v3')->group(...)` próprio, mas são incluídos via `require` dentro do grupo `api/v3` do `web.php`. Resultado: path `/api/v3/api/v3/rota` + middleware duplicado.

**Correção:** Remover o wrapper `Route::middleware(...)->prefix('api/v3')->group(...)` de ambos os arquivos. Os `Route::get/post/patch` herdando o contexto do grupo pai do `web.php` já é suficiente.

---

## 2. SEGURANÇA

### SEC-01 🔴 CRÍTICO — Rota `/dev/set-senha/{login}/{senha}` sem autenticação

**Arquivo:** `routes/web.php`

Qualquer pessoa com a URL redefine a senha de qualquer usuário e ativa a conta. **Deletar imediatamente** ou envolver em `app()->isLocal()`.

---

### SEC-02 🔴 CRÍTICO — Todas as rotas `/dev/*` expostas via condição frágil

**Problema:** `if (app()->environment('production')) abort(404)`. Um erro de `APP_ENV` expõe criação de admin e diagnóstico sem autenticação.

```php
if (app()->isLocal() || app()->environment('development', 'testing')) {
    Route::prefix('dev')->group(function () {
        Route::get('/ping-db', ...);
        Route::get('/criar-admin', ...);
        Route::get('/seed-dados', ...);
        // NÃO incluir set-senha aqui — deletar definitivamente
    });
}
```

---

### SEC-03 🔴 CRÍTICO — JWT do app de ponto usa APP_KEY como segredo HMAC

**Arquivo:** `routes/ponto_app.php`

O `APP_KEY` é compartilhado com sessões e criptografia. Se vazar, compromete tudo.

```php
// .env:
PONTO_APP_JWT_SECRET=gere_64_chars_aleatorios_aqui

// config/services.php:
'ponto_app' => ['jwt_secret' => env('PONTO_APP_JWT_SECRET')],

// ponto_app.php:
$secret = config('services.ponto_app.jwt_secret');
if (!$secret) abort(500, 'JWT secret não configurado');
```

---

### SEC-04 🟠 Trilha de auditoria — tabela existe mas nada grava nela

A migration `2026_02_22_000002_create_audit_table.php` cria `AUDIT_LOG`, mas nenhum código usa. Em gestão pública, alterações em salário, lotação, consignação e exoneração precisam de rastreabilidade para TCE-MA/CGM.

```php
// app/Http/Middleware/AuditLog.php
public function handle(Request $request, Closure $next)
{
    $response = $next($request);
    if (in_array($request->method(), ['POST','PUT','PATCH','DELETE']) && Auth::check()) {
        DB::table('AUDIT_LOG')->insert([
            'USUARIO_ID'  => Auth::id(),
            'ACAO'        => $request->method() . ' ' . $request->path(),
            'TABELA'      => $this->inferirTabela($request->path()),
            'DADOS_NOVOS' => json_encode($request->except(['_token', 'USUARIO_SENHA'])),
            'IP'          => $request->ip(),
            'USER_AGENT'  => substr($request->userAgent() ?? '', 0, 200),
            'created_at'  => now(),
        ]);
    }
    return $response;
}
```

Registrar no grupo `api/v3`: `->middleware(['web', 'auth', 'audit'])`.

---

### SEC-05 🟠 Sem rate limiting no login

```php
Route::prefix('api/auth')->middleware(['web', 'throttle:10,1'])->group(function () {
    Route::post('/login', ...);
});
```

---

### SEC-06 🟠 Admin hardcoded sem log de acesso

```php
if (strtolower($user->USUARIO_LOGIN) === 'admin') {
    $perfilNome = 'admin';
    \Log::channel('security')->warning('Login admin hardcoded', [
        'ip'         => request()->ip(),
        'at'         => now()->toIso8601String(),
        'user_agent' => request()->userAgent(),
    ]);
}
```

Criar canal em `config/logging.php`:
```php
'security' => ['driver' => 'daily', 'path' => storage_path('logs/security.log'), 'days' => 90],
```

---

### SEC-07 🟡 Senhas MD5 não migradas para usuários sem login recente

```php
// database/seeders/MigrarSenhasMd5Seeder.php
\App\Models\Usuario::all()->each(function($u) {
    if (preg_match('/^[a-f0-9]{32}$/', $u->USUARIO_SENHA)) {
        $u->USUARIO_ALTERAR_SENHA = 1; // força troca no próximo acesso
        $u->save();
    }
});
// php artisan db:seed --class=MigrarSenhasMd5Seeder
```

---

### SEC-08 🟡 Controle de acesso por secretaria ausente no módulo de folha

Ao implementar `GET /folha/por-secretaria`, validar `UNIDADE_ID` contra perfil do usuário — não apenas contra o parâmetro da query string:

```php
if (strtolower($user->USUARIO_LOGIN) !== 'admin') {
    $unidadesPermitidas = DB::table('USUARIO_PERFIL_UNIDADE')
        ->where('USUARIO_ID', $user->USUARIO_ID)
        ->pluck('UNIDADE_ID');
    if (!$unidadesPermitidas->contains($request->unidade_id)) {
        return response()->json(['erro' => 'Acesso negado a esta unidade.'], 403);
    }
}
```

---

## 3. CONSIGNAÇÃO — REFATORAÇÃO COMPLETA

O módulo tem banco correto mas a implementação está aquém do exigido para gestão pública.

---

### CONSIG-01 🔴 Margem única de 35% — separação 30%+5% não implementada

**Contexto legal:** 30% para empréstimos (BANCO, SINDICATO, COOPERATIVA) + 5% exclusivo para CARTAO. O campo `CONVENIO_TIPO` já existe na migration mas o cálculo ignora isso.

**Função auxiliar para usar nos dois endpoints:**
```php
function calcularMargem($funcionario_id) {
    $liquido = (float) DB::table('DETALHE_FOLHA as df')
        ->join('FOLHA as fo', 'fo.FOLHA_ID', '=', 'df.FOLHA_ID')
        ->where('df.FUNCIONARIO_ID', $funcionario_id)
        ->orderBy('fo.FOLHA_COMPETENCIA', 'desc')
        ->value(DB::raw('COALESCE(df.DETALHE_FOLHA_LIQUIDO, 0)'));

    $usado_emp = (float) DB::table('CONSIG_CONTRATO as c')
        ->join('CONSIG_CONVENIO as cv', 'cv.CONVENIO_ID', '=', 'c.CONVENIO_ID')
        ->where('c.FUNCIONARIO_ID', $funcionario_id)->where('c.STATUS', 'ATIVO')
        ->whereIn('cv.CONVENIO_TIPO', ['BANCO','SINDICATO','COOPERATIVA'])
        ->sum('c.VALOR_PARCELA');

    $usado_cartao = (float) DB::table('CONSIG_CONTRATO as c')
        ->join('CONSIG_CONVENIO as cv', 'cv.CONVENIO_ID', '=', 'c.CONVENIO_ID')
        ->where('c.FUNCIONARIO_ID', $funcionario_id)->where('c.STATUS', 'ATIVO')
        ->where('cv.CONVENIO_TIPO', 'CARTAO')
        ->sum('c.VALOR_PARCELA');

    return [
        'liquido'               => round($liquido, 2),
        'margem_emprestimo'     => round($liquido * 0.30, 2),
        'margem_cartao'         => round($liquido * 0.05, 2),
        'usado_emprestimo'      => round($usado_emp, 2),
        'usado_cartao'          => round($usado_cartao, 2),
        'disponivel_emprestimo' => round(($liquido * 0.30) - $usado_emp, 2),
        'disponivel_cartao'     => round(($liquido * 0.05) - $usado_cartao, 2),
    ];
}
```

**Validação no POST /consignacao:**
```php
$convenio = DB::table('CONSIG_CONVENIO')->find($request->convenio_id);
$m = calcularMargem($request->funcionario_id);

if ($convenio->CONVENIO_TIPO === 'CARTAO') {
    if ($request->valor_parcela > $m['disponivel_cartao'] && $m['liquido'] > 0) {
        return response()->json(['aviso' => 'Margem de cartão insuficiente (limite: 5%)',
            'disponivel' => $m['disponivel_cartao']], 422);
    }
} else {
    if ($request->valor_parcela > $m['disponivel_emprestimo'] && $m['liquido'] > 0) {
        return response()->json(['aviso' => 'Margem de empréstimo insuficiente (limite: 30%)',
            'disponivel' => $m['disponivel_emprestimo']], 422);
    }
}
```

**ConsignacaoView.vue** — exibir duas barras separadas na aba Margem:
```
Empréstimos: [===------] R$ 900 / R$ 1.500  (30%)
Cartão:      [=--------] R$ 50  / R$   250  ( 5%)
```

---

### CONSIG-02 🔴 Sem fluxo de autorização — contrato entra direto como ATIVO

Fluxo correto: `SOLICITADO → EM_ANALISE → AUTORIZADO → ATIVO`

**Migration adicional:**
```php
// adicionar em CONSIG_CONTRATO:
$table->string('STATUS_AUTORIZACAO', 30)->default('SOLICITADO');
$table->unsignedInteger('AUTORIZADO_POR')->nullable();
$table->timestamp('AUTORIZADO_EM')->nullable();
$table->text('MOTIVO_REJEICAO')->nullable();

// nova tabela de ocorrências:
Schema::create('CONSIG_OCORRENCIA', function (Blueprint $table) {
    $table->increments('OCORRENCIA_ID');
    $table->unsignedInteger('CONTRATO_ID');
    $table->string('TIPO', 30); // AUTORIZACAO | SUSPENSAO | REATIVACAO | CANCELAMENTO | QUITACAO
    $table->text('DESCRICAO')->nullable();
    $table->string('MOTIVO', 100)->nullable();
    $table->date('DATA_INICIO_EFEITO')->nullable();
    $table->date('DATA_FIM_EFEITO')->nullable();
    $table->unsignedInteger('USUARIO_ID')->nullable();
    $table->timestamps();
});
```

**Endpoints:**
```php
Route::patch('/consignacao/{id}/autorizar', function ($id) {
    $user = Auth::user();
    DB::table('CONSIG_CONTRATO')->where('CONTRATO_ID', $id)->update([
        'STATUS' => 'ATIVO', 'STATUS_AUTORIZACAO' => 'AUTORIZADO',
        'AUTORIZADO_POR' => $user->USUARIO_ID, 'AUTORIZADO_EM' => now(),
    ]);
    DB::table('CONSIG_OCORRENCIA')->insert([
        'CONTRATO_ID' => $id, 'TIPO' => 'AUTORIZACAO',
        'DESCRICAO' => 'Contrato autorizado pelo RH', 'USUARIO_ID' => $user->USUARIO_ID,
        'created_at' => now(),
    ]);
    return response()->json(['ok' => true]);
});

Route::patch('/consignacao/{id}/rejeitar', function (Request $request, $id) {
    DB::table('CONSIG_CONTRATO')->where('CONTRATO_ID', $id)->update([
        'STATUS' => 'CANCELADO', 'STATUS_AUTORIZACAO' => 'REJEITADO',
        'MOTIVO_REJEICAO' => $request->motivo,
    ]);
    return response()->json(['ok' => true]);
});
```

---

### CONSIG-03 🔴 Parcelas não descontadas automaticamente no cálculo da folha

No `POST /folhas/calcular`, após calcular os totais, adicionar:

```php
$parcelas = DB::table('CONSIG_PARCELA as cp')
    ->join('CONSIG_CONTRATO as cc', 'cc.CONTRATO_ID', '=', 'cp.CONTRATO_ID')
    ->where('cp.COMPETENCIA', $competencia_aaaa_mm)
    ->where('cp.STATUS', 'PENDENTE')->where('cc.STATUS', 'ATIVO')
    ->select('cp.*', 'cc.FUNCIONARIO_ID', 'cc.CONTRATO_ID as cc_id')
    ->get();

foreach ($parcelas as $p) {
    DB::table('DETALHE_FOLHA')
        ->where('FOLHA_ID', $folha->FOLHA_ID)
        ->where('FUNCIONARIO_ID', $p->FUNCIONARIO_ID)
        ->increment('DETALHE_FOLHA_DESCONTOS', $p->VALOR_PARCELA);
    DB::table('DETALHE_FOLHA')
        ->where('FOLHA_ID', $folha->FOLHA_ID)
        ->where('FUNCIONARIO_ID', $p->FUNCIONARIO_ID)
        ->decrement('DETALHE_FOLHA_LIQUIDO', $p->VALOR_PARCELA);

    DB::table('CONSIG_PARCELA')->where('PARCELA_ID', $p->PARCELA_ID)->update([
        'STATUS' => 'DESCONTADA', 'VALOR_PAGO' => $p->VALOR_PARCELA, 'updated_at' => now(),
    ]);
    DB::table('CONSIG_CONTRATO')->where('CONTRATO_ID', $p->cc_id)->update([
        'PARCELAS_PAGAS' => DB::raw('PARCELAS_PAGAS + 1'),
        'SALDO_DEVEDOR'  => DB::raw('SALDO_DEVEDOR - ' . $p->VALOR_PARCELA),
        'updated_at' => now(),
    ]);
    // Quitar se acabou
    $c = DB::table('CONSIG_CONTRATO')->where('CONTRATO_ID', $p->cc_id)->first();
    if ($c && $c->PARCELAS_PAGAS >= $c->PRAZO_MESES) {
        DB::table('CONSIG_CONTRATO')->where('CONTRATO_ID', $p->cc_id)
            ->update(['STATUS' => 'QUITADO', 'updated_at' => now()]);
    }
}
```

---

### CONSIG-04 🟠 Suspensão sem motivo, responsável ou prazo

```php
// Substituir PATCH /consignacao/{id}/status:
Route::patch('/consignacao/{id}/status', function (Request $request, $id) {
    $novoStatus = $request->status;
    DB::table('CONSIG_CONTRATO')->where('CONTRATO_ID', $id)
        ->update(['STATUS' => $novoStatus, 'updated_at' => now()]);

    if ($novoStatus === 'SUSPENSO') {
        DB::table('CONSIG_PARCELA')->where('CONTRATO_ID', $id)->where('STATUS', 'PENDENTE')
            ->update(['STATUS' => 'SUSPENSA', 'updated_at' => now()]);
    }
    if ($novoStatus === 'ATIVO') {
        DB::table('CONSIG_PARCELA')->where('CONTRATO_ID', $id)->where('STATUS', 'SUSPENSA')
            ->update(['STATUS' => 'PENDENTE', 'updated_at' => now()]);
    }

    DB::table('CONSIG_OCORRENCIA')->insert([
        'CONTRATO_ID'        => $id,
        'TIPO'               => strtoupper($novoStatus),
        'DESCRICAO'          => $request->descricao,
        'MOTIVO'             => $request->motivo,
        'DATA_INICIO_EFEITO' => $request->data_inicio_efeito ?? now()->format('Y-m-d'),
        'DATA_FIM_EFEITO'    => $request->data_fim_efeito,
        'USUARIO_ID'         => Auth::id(),
        'created_at'         => now(),
    ]);
    return response()->json(['ok' => true]);
});
```

---

### CONSIG-05 🟠 Relatório insuficiente para TCE-MA — sem granularidade por servidor

```php
Route::get('/consignacao/relatorio-analitico', function (Request $request) {
    $comp = $request->competencia ?? now()->format('Y-m');
    $dados = DB::table('CONSIG_PARCELA as cp')
        ->join('CONSIG_CONTRATO as cc', 'cc.CONTRATO_ID', '=', 'cp.CONTRATO_ID')
        ->join('CONSIG_CONVENIO as cv', 'cv.CONVENIO_ID', '=', 'cc.CONVENIO_ID')
        ->join('FUNCIONARIO as f', 'f.FUNCIONARIO_ID', '=', 'cc.FUNCIONARIO_ID')
        ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
        ->where('cp.COMPETENCIA', $comp)
        ->select('p.PESSOA_NOME as nome', 'f.FUNCIONARIO_MATRICULA as matricula',
                 'p.PESSOA_CPF_NUMERO as cpf', 'cv.CONVENIO_NOME as credor',
                 'cv.CONVENIO_TIPO as tipo', 'cc.NUMERO_CONTRATO',
                 'cp.NUMERO_PARCELA', 'cc.PRAZO_MESES',
                 'cp.VALOR_PARCELA as valor_desconto', 'cc.SALDO_DEVEDOR', 'cp.STATUS')
        ->orderBy('p.PESSOA_NOME')->get();
    return response()->json([
        'competencia' => $comp,
        'servidores'  => $dados,
        'totais'      => [
            'total_descontado' => $dados->where('STATUS','DESCONTADA')->sum('valor_desconto'),
            'total_pendente'   => $dados->where('STATUS','PENDENTE')->sum('valor_desconto'),
            'qtd_servidores'   => $dados->unique('matricula')->count(),
        ],
    ]);
});
```

**ConsignacaoView.vue** — botão exportar CSV:
```js
const exportarCSV = async () => {
    const { data } = await api.get(`/api/v3/consignacao/relatorio-analitico?competencia=${comp.value}`)
    const cols = ['Nome','Matrícula','CPF','Credor','Tipo','Nº Contrato','Parcela','Prazo','Valor','Saldo','Status']
    const rows = data.servidores.map(s => [s.nome,s.matricula,s.cpf,s.credor,s.tipo,
        s.NUMERO_CONTRATO,s.NUMERO_PARCELA,s.PRAZO_MESES,s.valor_desconto,s.SALDO_DEVEDOR,s.STATUS])
    const csv = [cols, ...rows].map(l => l.join(';')).join('\n')
    const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' })
    const a = document.createElement('a')
    a.href = URL.createObjectURL(blob); a.download = `consignacoes_${comp.value}.csv`; a.click()
}
```

---

## 4. GAPS DE BACKEND

### GAP-01 🟠 `/notificacoes` — polling ativo retorna 404 constante

```php
Route::get('/notificacoes', fn() => response()->json(['notificacoes' => [], 'nao_lidas' => 0]));
Route::post('/notificacoes/{id}/ler', fn($id) => response()->json(['ok' => true]));
Route::post('/notificacoes/ler-todas',  fn()    => response()->json(['ok' => true]));
```

**DashboardLayout.vue** — pausar quando aba em background:
```js
notifInterval = setInterval(() => {
    if (!document.hidden) fetchNotif()
}, 60_000)
onUnmounted(() => clearInterval(notifInterval))
```

---

### GAP-02 🟠 Folha — `GET/POST /folhas` e `/folhas/{id}/detalhes` não existem

```php
// GET /folhas
Route::get('/folhas', function () {
    $folhas = DB::table('FOLHA as f')
        ->leftJoin('DETALHE_FOLHA as df', 'df.FOLHA_ID', '=', 'f.FOLHA_ID')
        ->groupBy('f.FOLHA_ID', 'f.FOLHA_COMPETENCIA', 'f.FOLHA_TIPO_ESPECIAL', 'f.FOLHA_SITUACAO')
        ->select('f.FOLHA_ID', 'f.FOLHA_COMPETENCIA', 'f.FOLHA_TIPO_ESPECIAL', 'f.FOLHA_SITUACAO',
            DB::raw('COUNT(DISTINCT df.FUNCIONARIO_ID) as qtd_funcionarios'),
            DB::raw('SUM(COALESCE(df.DETALHE_FOLHA_PROVENTOS,0)) as total_proventos'),
            DB::raw('SUM(COALESCE(df.DETALHE_FOLHA_DESCONTOS,0)) as total_descontos'),
            DB::raw('SUM(COALESCE(df.DETALHE_FOLHA_LIQUIDO,0))   as total_liquido'))
        ->orderByDesc('f.FOLHA_COMPETENCIA')->limit(24)->get();
    return response()->json(['folhas' => $folhas]);
});

// POST /folhas/calcular
Route::post('/folhas/calcular', function (Request $request) {
    $comp = str_replace('-', '', $request->competencia); // AAAA-MM → AAAAMM
    $folha = DB::table('FOLHA')->where('FOLHA_COMPETENCIA', $comp)->first();
    if (!$folha) return response()->json(['erro' => 'Folha não encontrada.'], 404);

    DB::statement("UPDATE DETALHE_FOLHA SET
        DETALHE_FOLHA_LIQUIDO = DETALHE_FOLHA_PROVENTOS - DETALHE_FOLHA_DESCONTOS
        WHERE FOLHA_ID = ?", [$folha->FOLHA_ID]);

    // ** INCLUIR AQUI o bloco de desconto de consignações (CONSIG-03) **

    $totais = DB::table('DETALHE_FOLHA')->where('FOLHA_ID', $folha->FOLHA_ID)
        ->selectRaw('COUNT(DISTINCT FUNCIONARIO_ID) as qtd, SUM(DETALHE_FOLHA_LIQUIDO) as liquido')
        ->first();
    return response()->json([
        'ok' => true, 'mensagem' => "Folha {$request->competencia} calculada.",
        'qtd_funcionarios' => $totais->qtd ?? 0, 'total_liquido' => $totais->liquido ?? 0,
    ]);
});

// GET /folhas/{id}/detalhes
Route::get('/folhas/{id}/detalhes', function ($id) {
    $detalhes = DB::table('DETALHE_FOLHA as df')
        ->join('FUNCIONARIO as f', 'f.FUNCIONARIO_ID', '=', 'df.FUNCIONARIO_ID')
        ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
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

### GAP-03 🟠 `/servidores/buscar` faltando — views usam `/exoneracao/buscar` incorretamente

```php
Route::get('/servidores/buscar', function (Request $request) {
    $q = $request->q ?? '';
    return response()->json([
        'servidores' => DB::table('FUNCIONARIO as f')
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
            ->select('f.FUNCIONARIO_ID as id', 'p.PESSOA_NOME as nome',
                     'f.FUNCIONARIO_MATRICULA as matricula', 'f.FUNCIONARIO_DATA_INICIO as admissao',
                     'f.FUNCIONARIO_REGIME_PREV as regime_prev', 'c.CARGO_NOME as cargo',
                     'c.CARGO_SALARIO', 'f.CARGO_ID', 'f.CARREIRA_ID',
                     'f.FUNCIONARIO_CLASSE', 'f.FUNCIONARIO_REFERENCIA',
                     's.SETOR_NOME as setor', 'u.UNIDADE_NOME as secretaria', 'u.UNIDADE_ID as unidade_id')
            ->limit(15)->get()
    ]);
});
```

**Atualizar todas as views** para usar `/api/v3/servidores/buscar`.

---

### GAP-04 🟠 `/secretarias` — FolhaPagamentoView quebra no mount

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

### GAP-05 🟡 Banco de Horas sem backend

```php
// Migration:
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

// Endpoints (routes/banco_horas.php):
Route::get('/banco-horas', fn(Request $r) => response()->json([
    'saldo'    => DB::table('BANCO_HORAS')->where('FUNCIONARIO_ID', $r->funcionario_id)->orderByDesc('COMPETENCIA')->first(),
    'historico'=> DB::table('BANCO_HORAS')->where('FUNCIONARIO_ID', $r->funcionario_id)->orderByDesc('COMPETENCIA')->limit(12)->get(),
]));
Route::post('/banco-horas/compensar', function (Request $r) { /* lançar débito */ });
Route::get('/banco-horas/relatorio',  function (Request $r) { /* consolidado por secretaria */ });
```

---

### GAP-06 🟡 Atestados Médicos sem backend

```php
// Migration:
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

// Endpoints:
Route::get('/atestados', ...);               // lista com filtro perfil
Route::post('/atestados', ...);              // registrar
Route::patch('/atestados/{id}/validar', ...); // RH valida
```

---

### GAP-07 🟡 Holerite PDF — botão existe mas retorna 404

```php
Route::get('/meus-holerites/{id}/pdf', function ($id) {
    $detalhe = DB::table('DETALHE_FOLHA as df')
        ->join('FOLHA as f', 'f.FOLHA_ID', '=', 'df.FOLHA_ID')
        ->join('PESSOA as p', 'p.PESSOA_ID', '=',
               DB::raw('(SELECT PESSOA_ID FROM FUNCIONARIO WHERE FUNCIONARIO_ID = df.FUNCIONARIO_ID LIMIT 1)'))
        ->where('df.DETALHE_FOLHA_ID', $id)->first();
    if (!$detalhe) abort(404);
    // composer require barryvdh/laravel-dompdf
    $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.holerite', ['detalhe' => $detalhe]);
    return $pdf->download("holerite_{$detalhe->FOLHA_COMPETENCIA}.pdf");
});
```

---

### GAP-08 🟡 `/folha/por-secretaria` — endpoint referenciado mas não existe

Implementar com validação de acesso por unidade (ver SEC-08).

---

### GAP-09 🟠 Acumulação de Cargos — view criada, backend faltando

```php
// routes/acumulacao.php
Route::get('/acumulacao',             ...); // lista declarações com status
Route::post('/acumulacao',            ...); // servidor declara cargo externo
Route::patch('/acumulacao/{id}/analisar', ...); // RH analisa (APROVADO | IRREGULAR | SUSPENSO)
Route::get('/acumulacao/irregulares', ...); // relatório para CGM/CGJ
```

---

### GAP-10 🟠 Transparência Pública — view criada, backend faltando

```php
// routes/transparencia.php
Route::post('/transparencia/exportar', ...); // gera CSV/JSON da competência
Route::get('/transparencia/historico', ...); // lista exportações anteriores
Route::get('/transparencia/download/{id}', ...);
```

---

### GAP-11 🟠 PSS/Concurso — view criada, backend faltando

```php
// routes/pss.php
Route::get('/pss/editais',                    ...);
Route::post('/pss/editais',                   ...);
Route::post('/pss/editais/{id}/publicar',     ...);
Route::get('/pss/editais/{id}/candidatos',    ...);
Route::post('/pss/candidatos',                ...);
Route::patch('/pss/candidatos/{id}/convocar', ...);
Route::post('/pss/candidatos/{id}/nomear',    ...); // cria FUNCIONARIO+PESSOA+LOTACAO automaticamente
```

---

### GAP-12 🟠 Terceirizados — view criada, backend faltando

```php
// routes/terceirizados.php
Route::get('/terceirizados/empresas',                   ...);
Route::post('/terceirizados/empresas',                  ...);
Route::get('/terceirizados/postos',                     ...);
Route::post('/terceirizados/postos/{id}/checklist',     ...);
Route::get('/terceirizados/inadimplentes',              ...);
```

---

### GAP-13 🟠 SAGRES/TCE-MA — view criada, geração real com DB faltando

```php
// routes/sagres.php
Route::get('/sagres/preview',          ...); // preview antes de gerar
Route::post('/sagres/gerar',           ...); // cruzar DETALHE_FOLHA × SAGRES_EVENTO_DEPARA (seed pronto)
Route::get('/sagres/historico',        ...);
Route::get('/sagres/download/{id}',    ...);
```

---

## 5. PERFORMANCE

### PERF-01 🟡 Progressão funcional: N+1 na listagem admin

```php
$funcIds = $lista->pluck('FUNCIONARIO_ID');

$avaliacoes = DB::table('AVALIACAO_DESEMPENHO')
    ->whereIn('FUNCIONARIO_ID', $funcIds)
    ->orderByDesc('created_at')->get()
    ->groupBy('FUNCIONARIO_ID')->map(fn($g) => $g->first());

$comPenalidade = DB::table('AFASTAMENTO')
    ->whereIn('FUNCIONARIO_ID', $funcIds)
    ->where(fn($q) => $q
        ->whereRaw("LOWER(AFASTAMENTO_TIPO) LIKE '%disciplinar%'")
        ->orWhereRaw("LOWER(AFASTAMENTO_TIPO) LIKE '%suspen%'"))
    ->where(fn($q) => $q->whereNull('AFASTAMENTO_DATA_FIM')
        ->orWhere('AFASTAMENTO_DATA_FIM', '>=', now()))
    ->pluck('FUNCIONARIO_ID')->flip()->toArray();
// Usar $avaliacoes[$func->FUNCIONARIO_ID] e isset($comPenalidade[$funcId]) dentro do ->map()
```

---

### PERF-02 🟡 Índices de banco ausentes

```php
// Migration: 2026_03_12_add_performance_indexes.php
Schema::table('DETALHE_FOLHA', function (Blueprint $t) {
    $t->index(['FUNCIONARIO_ID', 'FOLHA_ID'], 'idx_df_func_folha');
    $t->index(['UNIDADE_ID', 'FOLHA_ID'],     'idx_df_unidade_folha');
});
Schema::table('FOLHA', function (Blueprint $t) {
    $t->index('FOLHA_COMPETENCIA', 'idx_folha_comp');
    $t->index(['FOLHA_COMPETENCIA', 'FOLHA_TIPO_ESPECIAL'], 'idx_folha_comp_tipo');
});
Schema::table('HORA_EXTRA',       fn($t) => $t->index(['COMPETENCIA', 'STATUS'], 'idx_he_comp_status'));
Schema::table('CONSIG_CONTRATO',  fn($t) => $t->index(['FUNCIONARIO_ID', 'STATUS'], 'idx_cc_func_status'));
Schema::table('CONSIG_PARCELA',   fn($t) => $t->index(['COMPETENCIA', 'STATUS'], 'idx_cp_comp_status'));
Schema::table('ESOCIAL_EVENTO',   fn($t) => $t->index(['FUNCIONARIO_ID', 'TIPO_EVENTO'], 'idx_es_func_tipo'));
Schema::table('LOTACAO',          fn($t) => $t->index(['FUNCIONARIO_ID', 'LOTACAO_DATA_FIM'], 'idx_lot_func_fim'));
```

---

### PERF-03 🟡 Alíquotas RPPS hardcoded (14%/28%)

```php
// routes/rpps.php — substituir:
$cfg = DB::table('RPPS_CONFIG')->orderByDesc('VIGENCIA_INICIO')->first();
$aliqServidor = ($cfg->ALIQUOTA_SERVIDOR ?? 14) / 100; // IPAM SLZ: 14%
$aliqPatronal = ($cfg->ALIQUOTA_PATRONAL ?? 22) / 100; // IPAM SLZ: 22% patronal
```

---

## 6. MÓDULOS LATENTES

Tabelas completamente definidas na migration `000009_create_modulos_avancados_tables.php`. Zero código de backend para a maioria.

### LAT-01 🔴 Acumulação de Cargos — obrigação constitucional (CF art. 37 XVI)
- **Frontend:** `AcumulacaoView.vue` ✅ criada (Sprint Antigravity)
- **Backend:** ❌ `routes/acumulacao.php` não existe → ver GAP-09

### LAT-02 🔴 Transparência Pública — exigência legal (LC 131/2009, Decreto 7.185/2010)
- **Frontend:** `TransparenciaView.vue` ✅ criada
- **Backend:** ❌ `routes/transparencia.php` não existe → ver GAP-10
- Impacta diretamente o índice TCE-MA (PMSLz: 97,4% em 2023)

### LAT-03 🟠 PSS / Processo Seletivo Simplificado
- **Frontend:** `PSSView.vue` ✅ criada
- **Backend:** ❌ `routes/pss.php` não existe → ver GAP-11
- **Integração crítica:** ao nomear candidato → criar `FUNCIONARIO + PESSOA + LOTACAO` automaticamente (evitar duplicidade de cadastro)

### LAT-04 🟠 Estagiários — Lei nº 11.788/2008
- **Frontend:** `EstagiariosView.vue` ✅ criada (alertas 30d, frequência mensal, cadastro)
- **Backend:** `routes/estagiarios.php` ✅ criado (Sprint Antigravity)
- **Status: ✅ COMPLETO**

### LAT-05 🟠 Terceirizados — checklist obrigatório para CGM
- **Frontend:** `TerceirizadosView.vue` ✅ criada (empresas, postos, checklist mensal)
- **Backend:** ❌ `routes/terceirizados.php` não existe → ver GAP-12

### LAT-06 🟠 SAGRES / Integração TCE-MA
- **Frontend:** `SagresView.vue` ✅ criada (de-para exibido, download XML mock local)
- **Seed:** `SAGRES_EVENTO_DEPARA` ✅ populado com códigos corretos
- **Backend:** ❌ `/api/v3/sagres/gerar` não existe — lógica real: cruzar `DETALHE_FOLHA` com `SAGRES_EVENTO_DEPARA` → ver GAP-13

---

## 7. ARQUITETURA

### ARQ-01 🟡 `web.php` com 512 KB — monolito ingerenciável

Refatoração gradual em arquivos modulares:
```
routes/
  web.php              ← SPA, auth, dev-only, require dos módulos
  funcionarios.php     ← GET/PUT /funcionarios, /documentos, /historico
  folha.php            ← /folhas, /meus-holerites, /calcular
  banco_horas.php      ← (criar)
  atestados.php        ← (criar)
  acumulacao.php       ← (criar)
  pss.php              ← (criar)
  estagiarios.php      ← ✅ criado
  terceirizados.php    ← (criar)
  transparencia.php    ← (criar)
  sagres.php           ← (criar)
```

**Regra absoluta para todos os arquivos require'd:** nunca reabrir grupo, nunca usar `Route::prefix()` ou `Route::middleware()` próprios. Herdar o contexto do grupo pai.

---

### ARQ-02 🟡 Acoplamento — views usando endpoint de outro módulo

`ExoneracaoView.vue`, `HoraExtraView.vue`, `DiariasView.vue` usam `/api/v3/exoneracao/buscar` como autocomplete de servidor. Após criar GAP-03 (`/servidores/buscar`), atualizar todas essas views.

---

### ARQ-03 🟡 Sem tabela de permissão por unidade

```php
Schema::create('USUARIO_UNIDADE_ACESSO', function (Blueprint $table) {
    $table->increments('ID');
    $table->unsignedInteger('USUARIO_ID');
    $table->unsignedInteger('UNIDADE_ID');
    $table->timestamps();
});
```

Necessário para SEC-08.

---

## 8. ERP/FISCAL — GAP TOTAL

Nenhuma migration, rota ou view existe. Implementar após consolidar módulos de RH.

**Sprint ERP-1: Orçamento (PPA/LOA)**
```
migrations: ORCAMENTO_PPA, ORCAMENTO_PROGRAMA, ORCAMENTO_ACAO, ORCAMENTO_LOA
routes/orcamento.php → GET /orcamento/ppa, GET /orcamento/loa?ano=, POST /orcamento/acao
views/financeiro/OrcamentoView.vue → PPA | LOA | Gráfico de Execução
```

**Sprint ERP-2: Execução da Despesa**
```
migrations: EMPENHO, LIQUIDACAO, PAGAMENTO_DESPESA
routes/execucao_despesa.php → GET/POST /empenho, POST /empenho/{id}/liquidar, POST /liquidacao/{id}/pagar
views/financeiro/ExecucaoDespesaView.vue → Empenhos | Liquidações | Pagamentos | Saldo por Ação
```

**Sprint ERP-3: Contabilidade Pública (PCASP)**
```
migrations: PCASP_CONTA (hierárquica), LANCAMENTO_CONTABIL
routes/contabilidade.php → GET /pcasp, POST /lancamentos, GET /balancete?mes=&ano=
views/financeiro/ContabilidadeView.vue → Plano de Contas | Lançamentos | Balancete
```

**Sprint ERP-4: Tesouraria**
```
migrations: CONTA_BANCARIA, MOVIMENTACAO_BANCARIA
routes/tesouraria.php → GET /contas-bancarias, GET /fluxo-caixa, POST /conciliar
views/financeiro/TesourariaView.vue → Contas | Extrato | Conciliação | Fluxo de Caixa
```

**Sprint ERP-5: Receita Municipal**
```
migrations: RECEITA_LANCAMENTO, RECEITA_DIVIDA_ATIVA
routes/receita_municipal.php → GET /receita, POST /receita, GET /receita/por-tipo
views/financeiro/ReceitaMunicipalView.vue
```

**Sprint ERP-6: Controle Externo**
```
migrations: SICONFI_ENVIO, RGF_DADOS, RREO_DADOS
routes/controle_externo.php → GET /sagres/preview, POST /sagres/gerar, GET /siconfi/rreo, GET /siconfi/rgf
views/financeiro/ControleExternoView.vue → SAGRES | SICONFI | RGF | RREO
```

**Para cada sprint, registrar no `web.php` e no `router/index.js`:**
```php
require __DIR__ . '/orcamento.php';
require __DIR__ . '/execucao_despesa.php';
// ...
```
```js
{ path: 'orcamento',         component: () => import('../views/financeiro/OrcamentoView.vue'),        meta: { roles: ['admin'] } },
{ path: 'execucao-despesa',  component: () => import('../views/financeiro/ExecucaoDespesaView.vue'),  meta: { roles: ['admin'] } },
{ path: 'contabilidade',     component: () => import('../views/financeiro/ContabilidadeView.vue'),    meta: { roles: ['admin'] } },
{ path: 'tesouraria',        component: () => import('../views/financeiro/TesourariaView.vue'),       meta: { roles: ['admin'] } },
{ path: 'receita-municipal', component: () => import('../views/financeiro/ReceitaMunicipalView.vue'), meta: { roles: ['admin'] } },
{ path: 'controle-externo',  component: () => import('../views/financeiro/ControleExternoView.vue'),  meta: { roles: ['admin'] } },
```

---

## 9. E-MAIL — BREVO ✅ PRONTO PARA IMPLEMENTAR

### Status do remetente
**`contato@rrtecnol.com.br`** — Verificado ✅ · DKIM `rrtecnol.com.br` ✅ · DMARC configurado ✅

Sem pendências. Aplicar na Sprint 4.

### Credenciais — copiar para .env

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp-relay.brevo.com
MAIL_PORT=587
MAIL_USERNAME=a11f6b001@smtp-brevo.com
MAIL_PASSWORD=<REDACTED — ver .env local>
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=contato@rrtecnol.com.br
MAIL_FROM_NAME="SISGEP — PMSLz"
ADMIN_NOTIF_EMAIL=ronaldo@rrtecnol.com.br
```

> ⚠️ Usar **`contato@rrtecnol.com.br`** — é o único endereço verificado no Brevo com DKIM+DMARC. Não trocar sem verificar o novo endereço no painel Brevo → Senders.

### Uso no Laravel

```php
// config/app.php:
'admin_notif_email' => env('ADMIN_NOTIF_EMAIL', 'ronaldo@rrtecnol.com.br'),

// Notificação de troca de senha:
Mail::send('emails.troca-senha', ['user' => $user, 'token' => $token], function ($m) use ($user) {
    $m->to($user->USUARIO_EMAIL ?? config('app.admin_notif_email'))
      ->subject('SISGEP — Solicitação de troca de senha');
});

// Notificação administrativa:
Mail::raw($mensagem, function ($m) {
    $m->to(config('app.admin_notif_email'))->subject('SISGEP — Notificação Administrativa');
});
```

### Onde será usado
- **SEC-07** — avisar usuários com MD5 para trocar senha no próximo acesso
- **Esqueci minha senha** — link de reset por e-mail
- **CONSIG-02** — notificação de autorização/rejeição de consignação
- **LAT-04** — alerta automático de contratos de estágio vencendo em 30 dias

---

## 10. CHECKLIST — SPRINTS

### 🚨 Sprint 0 — Segurança imediata (antes de subir em servidor)
- [x] SEC-01 — Deletar `/dev/set-senha` ✅ 2026-03-11 (envolvido em isLocal)
- [x] SEC-02 — Envolver `/dev/*` em `app()->isLocal()` ✅ 2026-03-11
- [x] SEC-03 — Chave JWT separada para app de ponto ✅ 2026-03-11 (fallback APP_KEY)
- [x] SEC-04 — Middleware AuditLog criado + alias 'audit' ✅ 2026-03-11
- [x] SEC-05 — Rate limiting throttle:10,1 no grupo api/auth ✅ 2026-03-11
- [x] SEC-06 — Canal 'security' no logging.php + log admin hardcoded ✅ 2026-03-11
- [x] SEC-07 — Seeder MigrarSenhasMd5 criado ✅ 2026-03-11
- [x] SEC-08 — USUARIO_UNIDADE_ACESSO migration + validação no folha/secretaria ✅ 2026-03-11

### 🔴 Sprint 1 — Bugs que quebram funcionalidade real
- [x] BUG-01 — Campos inexistentes no cálculo de margem ✅ 2026-03-11 (`consignacao.php`: DETALHE_FOLHA_LIQUIDO + margem 30%/5%)
- [x] BUG-02 — `hasAccess()` invertido no router ✅ já estava corrigido (<= não >=)
- [x] BUG-03 — Cache TTL 5min no `fetchUser()` ✅ já estava correto
- [x] BUG-04 — Substituir fetch() manual por axios ✅ 2026-03-11 (ConsignacaoView, HoraExtraView, ExoneracaoView, ESocialView, DiariasView, DeclaracoesRequerimentosView)
- [x] BUG-07 — Grupos duplicados diarias.php e rpps.php ✅ 2026-03-11 (rpps.php corrigido: Route::prefix próprio removido)
- [x] GAP-01 — Stub `/notificacoes` impl. + 3 rotas ✅ 2026-03-11 (web.php L757-765)

### 🟠 Sprint 2 — Consignação correta para gestão pública
- [x] CONSIG-01 — Separação 30% + 5% ✅ 2026-03-11 (consignacao.php + ConsignacaoView.vue: 2 barras separadas)
- [x] CONSIG-02 — Fluxo de autorização + tabela CONSIG_OCORRENCIA ✅ 2026-03-11
- [x] CONSIG-03 — Desconto automático de parcelas na folha ✅ 2026-03-11 (folha.php POST /folhas/calcular)
- [x] CONSIG-04 — Suspensão com rastreabilidade em CONSIG_OCORRENCIA ✅ 2026-03-11
- [x] CONSIG-05 — Relatório analítico CSV para TCE-MA ✅ 2026-03-11 (backend + exportarCSV no frontend)

### 🟠 Sprint 3 — Folha funcional + endpoints essenciais
- [x] GAP-02 — Endpoints de folha ✅ 2026-03-11 (folha.php: GET /folhas, GET /folhas/{id}/detalhes, POST /folhas/calcular)
- [x] GAP-03 — `/servidores/buscar` ✅ 2026-03-11 (web.php L771+)
- [x] GAP-04 — `/secretarias` ✅ 2026-03-11 (web.php L812+)
- [x] GAP-07 — PDF holerite ✅ 2026-03-11 (endpoint corrigido: view `v3.holerite-pdf`, busca `DETALHE_FOLHA`, DomPDF instalado)
- [x] BUG-05 — eSocial subquery O(n²) ✅ já estava correto (LEFT JOIN)
- [x] BUG-06 — Typo HISTORARIO ✅ já estava correto (HISTORICO)

### 🟠 Sprint 4 — Auditoria + segurança (ver Sprint 0 acima — todos concluídos)
- [x] SEC-04, SEC-06, SEC-07, SEC-08 ✅ 2026-03-11
- [x] ARQ-03 — USUARIO_UNIDADE_ACESSO ✅ 2026-03-11 (incluso em SEC-08)
- [x] Brevo `.env` configurado ✅ 2026-03-11

### 🟡 Sprint 5 — Performance + ponto
- [x] PERF-01 — N+1 progressão funcional ✅ 2026-03-11 (`/admin` e `/lista-elegiveis`: pré-fetch whereIn AVALIACAO_DESEMPENHO + AFASTAMENTO antes do loop)
- [x] PERF-02 — Índices de banco ✅ migration `2026_03_11_add_performance_indexes.php` existe
- [x] PERF-03 — Alíquotas RPPS hardcoded ✅ 2026-03-11 (rpps.php POST /rpps/calcular agora lê RPPS_CONFIG)
- [x] GAP-05 — Banco de Horas ✅ routes/banco_horas.php criado e registrado
- [x] GAP-06 — Atestados Médicos ✅ routes/atestados.php criado e registrado

### 🟠 Sprint 6 — Backends dos módulos Antigravity
- [x] GAP-09 — `routes/acumulacao.php` ✅ criado (133 linhas) e registrado no web.php
- [x] GAP-10 — `routes/transparencia.php` ✅ criado e registrado no web.php
- [x] GAP-11 — `routes/pss.php` ✅ criado e registrado no web.php
- [x] GAP-12 — `routes/terceirizados.php` ✅ criado e registrado no web.php
- [x] GAP-13 — `routes/sagres.php` ✅ criado e registrado no web.php

### 🟢 Sprint 7+ — Arquitetura + ERP
- [x] ARQ-01 — Extrair web.php em módulos ✅ 2026-03-11 (funcionarios.php + folha.php com require na L668-669)
- [x] ARQ-02 — Padronizar views para `/servidores/buscar` ✅ 2026-03-11
- [x] ERP Sprints 1–6 ✅ 2026-03-11

### 🔴 Sprint FOLHA — Amarrar motor de cálculo às estruturas do banco (15/03/2026)
- [ ] FOLHA-01 — Migration `VINCULO_FGTS/INSS/IRRF/REGIME` + Model fillable + FolhaParser usar flags ao invés de VinculoEnum por texto
- [ ] FOLHA-02 — Model `RppsConfig.php` + `TabelasImpostoService` ler alíquota de `RPPS_CONFIG` ao invés de constante hardcoded
- [ ] FOLHA-03 — Adicionar `EVENTO_INCIDE_*` ao `$fillable`/`$casts` do Model Evento + FolhaParser checar flags por evento
- [ ] FOLHA-04 — FolhaParser iterar `AtribuicaoLotacaoEvento` e adicionar rubricas fixas do contrato na folha (depende de FOLHA-01)
- [ ] FOLHA-05 — FolhaParser consultar `Tributacao` e `EventoVinculo` para tributação cruzada (depende de FOLHA-01 + FOLHA-03)
- [ ] FOLHA-06 — `TabelasImpostoService` ler `VigenciaImposto`/`TabelaImposto` + seed com tabela IRRF 2024 (depende de FOLHA-03)
- [ ] **FOLHA-VAL** — Validação paralela obrigatória: rodar parser e `sp_gera_folha` na mesma competência e cruzar totais por funcionário antes do corte

---

## 11. MAPA DE STATUS COMPLETO

| Módulo | Backend | Frontend | Migration | Status |
|--------|---------|----------|-----------|--------|
| Auth / Sessão | `web.php` | `LoginView.vue` | — | ✅ OK |
| Funcionários (CRUD) | `web.php` | `FuncionariosView.vue` | legacy | ✅ OK |
| Perfil Funcionário | `web.php` | `PerfilFuncionarioView.vue` | legacy | ✅ OK |
| Holerites (listagem + PDF) | `web.php` + `folha.php` | `ContraChequeView.vue` | — | ✅ OK (GAP-07 resolvido) |
| Folha Pagamento | `folha.php` ✅ | `FolhaPagamentoView.vue` | `000002` | ✅ OK (GAP-02 resolvido) |
| Exoneração | `exoneracao.php` | `ExoneracaoView.vue` | `000001,000005` | ✅ OK |
| Hora Extra | `hora_extra.php` | `HoraExtraView.vue` | `000006` | ✅ OK |
| Plantão Extra | `hora_extra.php` | `PlantoesExtrasView.vue` | `000006` | ✅ OK |
| Verba Indenizatória | `verba_indenizatoria.php` | `VerbaIndenizatoriaView.vue` | `000004` | ✅ OK |
| Consignação | `consignacao.php` | `ConsignacaoView.vue` | `000007` | ✅ CONSIG-01 a 05 OK |
| eSocial | `esocial.php` | `ESocialView.vue` | `000008` | ✅ OK (BUG-05 resolvido) |
| Progressão Funcional | `progressao_funcional.php` | `ProgressaoFuncionalView.vue` | `progressao_tables` | ✅ OK (BUG-06, PERF-01 resolvidos) |
| Diárias | `diarias.php` | `DiariasView.vue` ✅ | `000009` | ✅ OK (BUG-07, ARQ-02 resolvidos) |
| RPPS/IPAM | `rpps.php` | `RPPSView.vue` ✅ | `000009` | ✅ OK (BUG-07, PERF-03 resolvidos) |
| App Ponto Mobile | `ponto_app.php` | — | `000002,000003` | ✅ OK (SEC-03 resolvido) |
| Banco de Horas | `banco_horas.php` ✅ | `BancoHorasView.vue` | ✅ migration criada | ✅ OK (GAP-05 resolvido) |
| Atestados Médicos | `atestados.php` ✅ | `AtestadosMedicosView.vue` | ✅ migration criada | ✅ OK (GAP-06 resolvido) |
| Notificações | `web.php` stub ✅ | `DashboardLayout.vue` | — | ✅ OK (GAP-01 resolvido) |
| Secretarias (lookup) | `web.php` ✅ | múltiplas views | — | ✅ OK (GAP-04 resolvido) |
| **Acumulação de Cargos** | `acumulacao.php` ✅ | `AcumulacaoView.vue` ✅ | ✅ PRONTA | ✅ COMPLETO (GAP-09) |
| **Transparência Pública** | `transparencia.php` ✅ | `TransparenciaView.vue` ✅ | ✅ PRONTA | ✅ COMPLETO (GAP-10) |
| **PSS / Concurso** | `pss.php` ✅ | `PSSView.vue` ✅ | ✅ PRONTA | ✅ COMPLETO (GAP-11) |
| **Estagiários** | `estagiarios.php` ✅ | `EstagiariosView.vue` ✅ | ✅ PRONTA | ✅ COMPLETO |
| **Terceirizados** | `terceirizados.php` ✅ | `TerceirizadosView.vue` ✅ | ✅ PRONTA | ✅ COMPLETO (GAP-12) |
| **SAGRES / TCE-MA** | `sagres.php` ✅ | `SagresView.vue` ✅ | ✅ PRONTA (c/ seed) | ✅ COMPLETO (GAP-13) |
| **Orçamento Público** | `orcamento.php` ✅ | `OrcamentoView.vue` ✅ | ✅ batch 27 | ✅ COMPLETO |
| **Execução Despesa** | `execucao_despesa.php` ✅ | `ExecucaoDespesaView.vue` ✅ | ✅ batch 27 | ✅ COMPLETO |
| **Contabilidade PCASP** | `contabilidade.php` ✅ | `ContabilidadeView.vue` ✅ | ✅ batch 27 | ✅ COMPLETO |
| **Tesouraria** | `tesouraria.php` ✅ | `TesourariaView.vue` ✅ | ✅ batch 27 | ✅ COMPLETO |
| **Receita Municipal** | `receita_municipal.php` ✅ | `ReceitaMunicipalView.vue` ✅ | ✅ batch 27 | ✅ COMPLETO |
| **Controle Externo** | `controle_externo.php` ✅ | `ControleExternoView.vue` ✅ | ✅ batch 27 | ✅ COMPLETO |
| **Motor de Folha — VINCULO flags** | ❌ migration faltando | ✅ (front OK) | ❌ colunas ausentes | ❌ FOLHA-01 |
| **Motor de Folha — RPPS_CONFIG** | ❌ sem model | — | ✅ tabela existe | ❌ FOLHA-02 |
| **Motor de Folha — EVENTO_INCIDE_*** | ❌ sem fillable | — | ✅ colunas existem | ❌ FOLHA-03 |
| **Motor de Folha — AtribuicaoLotacaoEvento** | ✅ model OK | — | ✅ tabela OK | ❌ FOLHA-04 (não lido pelo parser) |
| **Motor de Folha — Tributacao/EventoVinculo** | ✅ models OK | — | ✅ tabelas OK | ❌ FOLHA-05 (não consultado) |
| **Motor de Folha — VigenciaImposto** | ✅ model OK | — | ✅ tabela OK | ❌ FOLHA-06 (tabelas IRRF/INSS hardcoded) |

---

## 12. DESCONEXÕES MOTOR DE FOLHA

> **Contexto:** Auditoria realizada em 15/03/2026. O `FolhaParserService` foi escrito com lógica simplificada e hardcoded sem consumir estruturas de dados que já existem no banco. São dois mundos paralelos: banco + models ricos de um lado, motor de cálculo ingênuo do outro. As 6 desconexões abaixo devem ser resolvidas antes da virada definitiva da `sp_gera_folha`.

---

### FOLHA-01 🔴 `VINCULO_FGTS / VINCULO_INSS / VINCULO_IRRF` — campos que nunca foram criados no banco

**Situação:**
- **Frontend** (`VinculosView.vue`): checkboxes "Sujeito ao FGTS", "Contribui INSS", "Sujeito ao IRRF" existem no modal de cadastro ✅
- **Model** (`Vinculo.php`): campos ausentes do `$fillable` e `$casts` ❌
- **Migration**: colunas **nunca criadas** em nenhuma migration ❌
- **`FolhaParserService`**: ignora completamente — usa `VinculoEnum::resolveVinculo()` que infere o regime por palavras-chave no texto da sigla/descrição ❌

**Impacto:** Os checkboxes são coletados pelo front, enviados para a API, e silenciosamente descartados pelo Eloquent. Nenhuma configuração de imposto persiste. A classificação de vínculo depende inteiramente do texto digitado pelo operador.

**O que implementar (Antygravity):**

*Migration:*
```php
Schema::table('VINCULO', function (Blueprint $table) {
    if (!Schema::hasColumn('VINCULO', 'VINCULO_FGTS'))
        $table->boolean('VINCULO_FGTS')->default(false)->after('VINCULO_SIGLA');
    if (!Schema::hasColumn('VINCULO', 'VINCULO_INSS'))
        $table->boolean('VINCULO_INSS')->default(true)->after('VINCULO_FGTS');
    if (!Schema::hasColumn('VINCULO', 'VINCULO_IRRF'))
        $table->boolean('VINCULO_IRRF')->default(true)->after('VINCULO_INSS');
    if (!Schema::hasColumn('VINCULO', 'VINCULO_REGIME'))
        $table->string('VINCULO_REGIME', 10)->default('RPPS')->after('VINCULO_IRRF'); // RPPS | RGPS | OUTRO
});
```

*Model `Vinculo.php` — adicionar ao `$fillable` e `$casts`:*
```php
protected $fillable = [
    "VINCULO_NOME", "VINCULO_DESCRICAO", "VINCULO_SIGLA", "VINCULO_ATIVO",
    "VINCULO_FGTS", "VINCULO_INSS", "VINCULO_IRRF", "VINCULO_REGIME",
];
protected $casts = [
    "VINCULO_ATIVO" => "integer",
    "VINCULO_FGTS"  => "boolean",
    "VINCULO_INSS"  => "boolean",
    "VINCULO_IRRF"  => "boolean",
];
```

*`FolhaParserService` — substituir o `match` de VinculoEnum por leitura direta:*
```php
// Em calcularRubricas(), após carregar $vinculo:
$pagaInss = (bool) ($vinculo?->VINCULO_INSS ?? true);
$pagaIrrf = (bool) ($vinculo?->VINCULO_IRRF ?? true);
$pagaFgts = (bool) ($vinculo?->VINCULO_FGTS ?? false);
$regime   = $vinculo?->VINCULO_REGIME ?? 'RPPS'; // RPPS | RGPS | OUTRO

// Substituir o match($tipoVinculo) por:
$resultado = $this->calcularPorFlags($salarioBase, $diasTrabalhados, $faltas, $regime, $pagaInss, $pagaIrrf, $pagaFgts);
```

---

### FOLHA-02 🔴 `RPPS_CONFIG` — tabela existe no banco, nunca é lida pelo motor

**Situação:**
- **Migration** (`2026_03_11_create_usuario_unidade_acesso_rpps_config.php`): tabela `RPPS_CONFIG` criada com seed de 14% servidor / 28% patronal ✅
- **Model**: nenhum `RppsConfig.php` existe em qualquer lugar ❌
- **`TabelasImpostoService`**: usa `const INSS_RPPS_ALIQUOTA = 0.14` — **constante hardcoded** ❌
- **`routes/rpps.php`**: já corrigido para ler `RPPS_CONFIG` (PERF-03 ✅) mas o `FolhaParserService` não faz o mesmo ❌

**Impacto:** Se a Prefeitura alterar a alíquota RPPS (ex: por portaria), o sistema exige alteração de código. A tabela configurável existe e não é usada.

**O que implementar:**

*Criar `app/Models/RppsConfig.php`:*
```php
class RppsConfig extends Model {
    protected $table = 'RPPS_CONFIG';
    protected $primaryKey = 'CONFIG_ID';
    public $timestamps = true;
    protected $fillable = ['ALIQUOTA_SERVIDOR','ALIQUOTA_PATRONAL','VIGENCIA_INICIO','VIGENCIA_FIM','OBSERVACAO'];

    public static function vigente(): self {
        return self::whereNull('VIGENCIA_FIM')->orWhere('VIGENCIA_FIM', '>=', now()->format('Y-m'))
            ->orderByDesc('VIGENCIA_INICIO')->first() ?? new self(['ALIQUOTA_SERVIDOR' => 14, 'ALIQUOTA_PATRONAL' => 28]);
    }
}
```

*`TabelasImpostoService` — substituir a constante:*
```php
public function calcularInssRpps(float $salarioBruto): float {
    $cfg = \App\Models\RppsConfig::vigente();
    $aliquota = ($cfg->ALIQUOTA_SERVIDOR ?? 14) / 100;
    return round($salarioBruto * $aliquota, 2);
}
```

---

### FOLHA-03 🔴 `EVENTO_INCIDE_INSS / EVENTO_INCIDE_IRRF / EVENTO_INCIDE_RPPS` — flags do evento ignoradas

**Situação:**
- **Migration** (`2026_03_11_000003_update_evento_fields.php`): colunas `EVENTO_INCIDE_INSS`, `EVENTO_INCIDE_IRRF`, `EVENTO_INCIDE_RPPS`, `EVENTO_CATEGORIA`, `EVENTO_CODIGO` criadas ✅
- **Model `Evento.php`**: campos ausentes do `$fillable` e dos `$casts` ❌
- **`FolhaParserService`**: nunca consulta essas flags — decide incidência pelo nome da rubrica ou pelo tipo de vínculo ❌

**Impacto:** A tela de cadastro de Eventos tem lógica de incidência tributária que nunca chega ao cálculo. Um evento pode estar marcado como "não incide INSS" e o motor vai calcular INSS sobre ele mesmo assim.

**O que implementar:**

*Model `Evento.php` — adicionar ao `$fillable` e `$casts`:*
```php
// Adicionar ao $fillable:
"EVENTO_CODIGO", "EVENTO_CATEGORIA",
"EVENTO_INCIDE_INSS", "EVENTO_INCIDE_IRRF", "EVENTO_INCIDE_RPPS",

// Adicionar ao $casts:
"EVENTO_INCIDE_INSS" => "boolean",
"EVENTO_INCIDE_IRRF" => "boolean",
"EVENTO_INCIDE_RPPS" => "boolean",
```

*`FolhaParserService` — consultar flags ao calcular base de INSS/IRRF:*
```php
// Ao acumular a base de cálculo para INSS, checar evento por evento:
foreach ($rubricas as $rubrica) {
    $evento = Evento::where('EVENTO_DESCRICAO', $rubrica['descricao'])->first();
    if ($evento?->EVENTO_INCIDE_INSS) $baseInss += $rubrica['valor'];
    if ($evento?->EVENTO_INCIDE_IRRF) $baseIrrf += $rubrica['valor'];
}
```

---

### FOLHA-04 🟡 `AtribuicaoLotacaoEvento` — rubricas fixas por contrato nunca entram na folha

**Situação:**
- **Model e tabela**: existem e completos — `ATRIBUICAO_LOTACAO_EVENTO` com `ATRIBUICAO_LOTACAO_EVENTO_VALOR`, vigência início/fim, `EVENTO_ID` ✅
- **Relação**: `AtribuicaoLotacao` carrega `atribuicaoLotacaoEventos` nos relacionamentos ✅
- **`FolhaParserService`**: só lê `ATRIBUICAO_LOTACAO_VALOR` (salário base) — **nunca itera os eventos vinculados ao contrato** ❌

**Impacto:** Adicionais permanentes, gratificações e rubricas fixas vinculadas ao contrato do servidor não entram na folha. O motor calcula apenas o vencimento base.

**O que implementar em `FolhaParserService`:**
```php
// Após calcular vencimento base, adicionar rubricas do contrato:
$eventosContrato = $atribuicaoLotacao->atribuicaoLotacaoEventos
    ->where('ATRIBUICAO_LOTACAO_EVENTO_EXCLUIDO', 0)
    ->filter(fn($e) =>
        (!$e->ATRIBUICAO_LOTACAO_EVENTO_FIM || $e->ATRIBUICAO_LOTACAO_EVENTO_FIM >= now()->format('Ym'))
        && $e->ATRIBUICAO_LOTACAO_EVENTO_INICIO <= now()->format('Ym')
    );

foreach ($eventosContrato as $eventoContrato) {
    $rubricas[] = [
        'descricao' => $eventoContrato->evento->EVENTO_DESCRICAO,
        'tipo'      => 'P',
        'valor'     => (float) $eventoContrato->ATRIBUICAO_LOTACAO_EVENTO_VALOR,
    ];
}
```

---

### FOLHA-05 🟡 `Tributacao` e `EventoVinculo` — regras de tributação cruzada ignoradas

**Situação:**
- **`Tributacao`**: define qual evento de imposto incide sobre qual evento de provento, por vínculo ✅
- **`EventoVinculo`**: define quais eventos são proibidos para determinado vínculo ✅
- **`Evento`**: carrega ambos nos relacionamentos (`tributacoes`, `eventosVinculos`) ✅
- **`FolhaParserService`**: não consulta nenhum dos dois — calcula INSS/IRRF com fórmulas fixas ❌

**Impacto:** As regras de tributação configuradas pelo RH na interface nunca são aplicadas. O motor sempre aplica a tabela hardcoded, ignorando qualquer configuração personalizada.

**Ordem de implementação:** Depende de FOLHA-01 e FOLHA-03 estarem prontos. Abordar em sprint posterior.

---

### FOLHA-06 🟡 `VigenciaImposto` + `TabelaImposto` — tabelas dinâmicas de imposto sem uso

**Situação:**
- **Models `VigenciaImposto` → `TabelaImposto`**: existem com estrutura completa ✅
- **`Evento`**: carrega `vigenciaImpostos` nos relacionamentos ✅
- **`TabelasImpostoService`**: tabelas IRRF e INSS RGPS são `const` PHP hardcoded ❌

**Impacto:** Quando o governo atualizar as faixas de IRRF ou INSS, o sistema exigirá alteração de código e novo deploy. As tabelas dinâmicas existem no banco e nunca são consultadas.

**O que implementar:**
```php
// TabelasImpostoService::calcularIrrf() — trocar constante por query:
private function tabelaIrrfVigente(): array {
    $vigencia = VigenciaImposto::where('VIGENCIA_IMPOSTO_INICIO', '<=', now()->format('Ym'))
        ->where(fn($q) => $q->whereNull('VIGENCIA_IMPOSTO_FIM')
            ->orWhere('VIGENCIA_IMPOSTO_FIM', '>=', now()->format('Ym')))
        ->with('tabelaImpostos')->orderByDesc('VIGENCIA_IMPOSTO_ID')->first();

    if (!$vigencia || $vigencia->tabelaImpostos->isEmpty()) {
        return self::IRRF_TABELA; // fallback para constante enquanto banco não tiver dados
    }
    return $vigencia->tabelaImpostos->map(fn($t) => [
        $t->TABELA_IMPOSTO_LIMITE, $t->TABELA_IMPOSTO_ALIQUOTA / 100, $t->TABELA_IMPOSTO_DEDUCAO
    ])->toArray();
}
```

**Seed necessário:** popular `VIGENCIA_IMPOSTO` e `TABELA_IMPOSTO` com os valores atuais da tabela IRRF 2024 para que o fallback nunca seja necessário em produção.

---

### Sprint FOLHA — Checklist de Implementação

| # | Item | Dependência | Prioridade |
|---|------|-------------|------------|
| FOLHA-01 | Migration VINCULO + Model + FolhaParser usar flags | — | 🔴 Alta |
| FOLHA-02 | Model RppsConfig + TabelasImpostoService ler banco | — | 🔴 Alta |
| FOLHA-03 | Model Evento fillable + FolhaParser checar EVENTO_INCIDE_* | — | 🔴 Alta |
| FOLHA-04 | FolhaParser iterar AtribuicaoLotacaoEvento | FOLHA-01 | 🟡 Média |
| FOLHA-05 | FolhaParser consultar Tributacao/EventoVinculo | FOLHA-01, FOLHA-03 | 🟡 Média |
| FOLHA-06 | TabelasImpostoService ler VigenciaImposto + seed dados | FOLHA-03 | 🟡 Média |
| — | Validação paralela: rodar GENTE v3 e sp_gera_folha na mesma competência e cruzar totais | Todos acima | 🔴 Obrigatório antes do corte |

---

*Gerado por análise completa: routes/*.php (13 arquivos), resources/gente-v3/src/views/** (todas as views), database/migrations/** (53 arquivos), store/auth.js, router/index.js*
*Última revisão: 2026-03-11 — todas as Sprints 0–7 concluídas. Próxima etapa: deploy VPS (ver `docs/checklist-deploy-vps.md`) ou ERP fase 2.*
*E-mail: Brevo pronto — `contato@rrtecnol.com.br` verificado, DKIM+DMARC ✅.*
